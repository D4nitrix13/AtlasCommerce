<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\ProductVariant;
use App\Services\Catalog\VariantFinishDeriver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Backfill de `product_variants.finish_slug` / `finish_label` cuando el
 * valor actual no es un acabado sino el slug del producto repetido.
 *
 * Diagnóstico Oct 2026 (La Voz Instrumentos, 346 productos / 411
 * variantes): 306 variantes tienen `finish_slug === products.slug`. La
 * PDP renderiza eso como etiqueta de acabado, o sea
 * "ACABADO: 1960 Les Paul Standard Reissue Iced Tea Burst Left
 * Handed" — el nombre entero del producto en el lugar del color. Las
 * otras 105 variantes ya traen un acabado real y no se tocan.
 *
 * Estrategia, en orden de preferencia:
 *
 *   1. Subdirectorio de variante en
 *      `assets/products/{tenant}/{brand}/{product}/{finish}/`. El
 *      nombre del directorio ES el acabado (`ebony`,
 *      `antique-natural`, …) porque lo puso el pipeline de assets.
 *   2. Vocabulario curado de acabados contra `products.name`. Lista
 *      explícita y cerrada (ver `VariantFinishDeriver`): si el nombre
 *      no matchea, la variante queda como está. Degradación honesta.
 *
 * Restricción de dominio: sólo se derivan acabados en categorías donde
 * el color del cuerpo es un concepto del dominio (`guitars`,
 * `basses`). En `cases`, `apparel`, `strings`, `parts`, `amps`,
 * `pedals`, `capos` el color es un atributo del producto, no un
 * acabado, y esas variantes se cuentan aparte como
 * `skipped_non_instrument`.
 *
 * Idempotente: sólo escribe cuando el par (slug, label) calculado
 * difiere del almacenado, así que la segunda corrida reporta
 * `variants_updated: 0`.
 *
 * Uso:
 *   php artisan catalog:backfill-variant-finish --dry-run
 *   php artisan catalog:backfill-variant-finish
 */
final class BackfillVariantFinishCommand extends Command
{
    protected $signature = 'catalog:backfill-variant-finish
        {--dry-run : Report what would change without writing}
        {--asset-base= : Path to repo assets/ (default: env ATLAS_ASSET_BASE or repo default)}
        {--product= : Limit to a single product slug}';

    protected $description = 'Derive real finish_slug/finish_label for variants whose finish is just the product slug.';

    public function handle(VariantFinishDeriver $deriver): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $productSlugFilter = $this->option(key: 'product');
        $tenantSlug = 'instrumentos-musicales-la-voz';

        $assetBase = $this->resolveAssetBase(
            explicit: $this->option(key: 'asset-base'),
            tenantSlug: $tenantSlug,
        );

        if ($assetBase === null) {
            $this->error(
                'No encontré el árbol de assets del tenant. Pasá --asset-base=<path> '
                .'o definí ATLAS_ASSET_BASE. Sin esa ruta la estrategia 1 '
                .'(subdirectorio de variante) no puede evaluarse y el comando '
                .'no debe seguir: reportaría 0 derivados de assets sin decir por qué.',
            );

            return self::FAILURE;
        }

        $this->info(string: sprintf(
            '=== BackfillVariantFinish (mode=%s, asset-base=%s) ===',
            $isDryRun ? 'DRY-RUN' : 'WRITE',
            $assetBase,
        ));

        /** @var array<string, list<string>> $brandByProductSlug */
        $brandByProductSlug = $this->buildBrandIndex(assetBase: $assetBase, tenantSlug: $tenantSlug);

        $stats = [
            'variants_scanned' => 0,
            'variants_updated' => 0,
            'fallback_candidates' => 0,
            'variants_already_real' => 0,
            'updated_from_asset_dir' => 0,
            'updated_from_name' => 0,
            'undecided_no_vocabulary_match' => 0,
            'undecided_no_asset_dir' => 0,
            'skipped_non_instrument' => 0,
            'skipped_multi_asset_dirs' => 0,
            'skipped_slug_collision' => 0,
            'errors' => [],
        ];

        /** @var list<string> $undecidedSamples */
        $undecidedSamples = [];

        $this->variantQuery(productSlug: $productSlugFilter)
            ->chunk(count: 100, callback: function ($variants) use (
                $deriver,
                $isDryRun,
                $assetBase,
                $tenantSlug,
                $brandByProductSlug,
                &$stats,
                &$undecidedSamples,
            ): void {
                foreach ($variants as $variant) {
                    $product = $variant->product;

                    if ($product === null) {
                        $stats['errors'][] = "Variant id={$variant->id} sin producto padre.";

                        continue;
                    }

                    $stats['variants_scanned']++;

                    // ─── Gate 1: ¿ya tiene un acabado real? ───────────
                    // Un finish distinto del slug del producto es un
                    // acabado real: no se toca nunca, aunque el
                    // vocabulario pudiera sugerir otro.
                    if ($variant->finish_slug !== $product->slug) {
                        $stats['variants_already_real']++;

                        continue;
                    }

                    // ─── Gate 2: ¿la categoría admite acabado? ──────
                    $categorySlug = $product->category?->slug;
                    if (! $deriver->isFinishableCategory(categorySlug: $categorySlug)) {
                        $stats['skipped_non_instrument']++;

                        continue;
                    }

                    $stats['fallback_candidates']++;

                    $fromAssets = $this->resolveFromAssetDir(
                        assetBase: $assetBase,
                        tenantSlug: $tenantSlug,
                        brandSlug: $brandByProductSlug[$product->slug][0] ?? null,
                        productSlug: (string) $product->slug,
                    );

                    // Más de un subdirectorio de acabado en disco = el
                    // producto tiene multi-acabado y la fila de BD no
                    // lo refleja. Elegir uno de los dos sería inventar:
                    // se saltea y se reporta.
                    if ($fromAssets === 'multi') {
                        $stats['skipped_multi_asset_dirs']++;

                        continue;
                    }

                    $derived = $fromAssets
                        ?? $deriver->deriveFromProductName(productName: (string) $product->name);

                    if ($derived === null) {
                        $stats['undecided_no_vocabulary_match']++;
                        $stats['undecided_no_asset_dir']++;
                        $undecidedSamples[] = "{$product->slug} → '{$product->name}'";

                        continue;
                    }

                    // Un slug de acabado que choca con otro acabado del
                    // mismo producto rompe el `?finish=` de la PDP. Se
                    // saltea y se reporta en vez de dejar dos finishes
                    // indistinguibles.
                    if ($this->collidesWithSibling(
                        variant: $variant,
                        derivedSlug: $derived['slug'],
                    )) {
                        $stats['skipped_slug_collision']++;

                        continue;
                    }

                    if ($variant->finish_slug === $derived['slug']
                        && $variant->finish_label === $derived['label']
                    ) {
                        // Ya se corrió sobre esta fila: no cuenta como
                        // update (idempotencia observable).
                        continue;
                    }

                    if ($fromAssets !== null) {
                        $stats['updated_from_asset_dir']++;
                    } else {
                        $stats['updated_from_name']++;
                    }

                    if (! $isDryRun) {
                        try {
                            $variant->forceFill([
                                'finish_slug' => $derived['slug'],
                                'finish_label' => $derived['label'],
                            ])->save();
                        } catch (Throwable $e) {
                            $stats['errors'][] = "Variant id={$variant->id}: ".$e->getMessage();

                            continue;
                        }
                    }

                    $stats['variants_updated']++;
                }
            });

        $this->line(string: sprintf('Variantes escaneadas:               %d', $stats['variants_scanned']));
        $this->line(string: sprintf('  ya con acabado real (intactas):  %d', $stats['variants_already_real']));
        $this->line(string: sprintf('  con finish_slug == products.slug: %d', $stats['fallback_candidates']));
        $this->line(string: sprintf('Variantes actualizadas:            %d', $stats['variants_updated']));
        $this->line(string: sprintf('  desde subdirectorio de assets:   %d', $stats['updated_from_asset_dir']));
        $this->line(string: sprintf('  desde vocabulario del nombre:    %d', $stats['updated_from_name']));
        $this->line(string: sprintf('Sin acabado derivable (degradan):  %d', $stats['undecided_no_vocabulary_match']));
        $this->line(string: sprintf('  y sin subdirectorio de assets:   %d', $stats['undecided_no_asset_dir']));
        $this->line(string: sprintf('Omitidas por categoría sin acabado: %d', $stats['skipped_non_instrument']));
        $this->line(string: sprintf('Omitidas por multi-acabado en disco: %d', $stats['skipped_multi_asset_dirs']));
        $this->line(string: sprintf('Omitidas por colisión de slug:    %d', $stats['skipped_slug_collision']));

        if ($undecidedSamples !== []) {
            $this->newLine();
            $this->comment('Muestra de variantes sin acabado derivable ('.count($undecidedSamples).' en total):');
            foreach (array_slice($undecidedSamples, 0, 20) as $sample) {
                $this->line('  - '.$sample);
            }
        }

        if ($stats['errors'] !== []) {
            $this->newLine();
            foreach ($stats['errors'] as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->newLine();
            $this->comment('DRY-RUN: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    /**
     * El nombre de un subdirectorio de variante es el acabado, porque
     * lo produjo el pipeline de assets con el mismo criterio de naming
     * que `assets-naming-policy.md`.
     *
     * Se ignora a propósito el directorio del producto cuando NO tiene
     * subdirectorios: ahí cuelgan los .webp del producto entero
     * (`discoverFromFilesystem()` de `CatalogAssetsSeeder` tiene el
     * mismo caso especial), y "el acabado es el nombre del producto" es
     * exactamente el fallback que este comando viene a eliminar.
     *
     * @return array{slug: string, label: string}|'multi'|null
     *                                                         `'multi'` cuando hay más de un acabado en disco: el
     *                                                         producto es multi-acabado y la fila de BD no lo refleja.
     *                                                         Es un estado, no un acabado — el caller lo saltea.
     */
    private function resolveFromAssetDir(
        string $assetBase,
        string $tenantSlug,
        ?string $brandSlug,
        string $productSlug,
    ): array|string|null {
        if ($brandSlug === null || $brandSlug === '') {
            return null;
        }

        $productDir = "{$assetBase}/products/{$tenantSlug}/{$brandSlug}/{$productSlug}";
        if (! is_dir($productDir)) {
            return null;
        }

        $variantDirs = glob($productDir.'/*', GLOB_ONLYDIR) ?: [];
        if ($variantDirs === []) {
            return null;
        }

        // Más de un subdirectorio = producto multi-acabado real. Ese
        // caso ya viene bien de la corrida del seeder; no se toca acá
        // para no pisar acabados que existen ni para adivinar cuál de
        // los dos le corresponde a esta fila.
        if (count($variantDirs) > 1) {
            return 'multi';
        }

        $finishSlug = basename($variantDirs[0]);

        return [
            'slug' => $finishSlug,
            'label' => ucwords(str_replace('-', ' ', $finishSlug)),
        ];
    }

    /**
     * ¿El slug derivado ya lo usa otra variante del mismo producto?
     */
    private function collidesWithSibling(ProductVariant $variant, string $derivedSlug): bool
    {
        return ProductVariant::query()
            ->where(column: 'product_id', operator: '=', value: $variant->product_id)
            ->where(column: 'id', operator: '!=', value: $variant->id)
            ->where(column: 'finish_slug', operator: '=', value: $derivedSlug)
            ->exists();
    }

    /**
     * Variantes cuyo `finish_slug` es (o puede ser) el slug del
     * producto. Trae las que ya tienen acabado real también, para que el
     * reporte las cuente como intactas y no como ignoradas.
     */
    private function variantQuery(string|bool|null $productSlug): Builder
    {
        $query = ProductVariant::query()->with(relations: ['product.category']);

        if (is_string($productSlug) && $productSlug !== '') {
            $query->whereHas(
                relation: 'product',
                callback: fn ($productQuery) => $productQuery
                    ->where(column: 'slug', operator: '=', value: $productSlug),
            );
        }

        return $query;
    }

    /**
     * Resuelve la raíz de assets del tenant.
     *
     * No hay un default único que valga en todos los entornos: en el
     * container de dev `assets/` está montada en `base_path('assets')`,
     * y en el checkout del host vive en `base_path('../assets')`. El
     * default de `base_path('../..')/assets` que usan los comandos
     * hermanos resuelve a `/var/assets` dentro del container, que NO
     * existe — y un path inexistente no lanza error, simplemente hace
     * que la estrategia 1 devuelva cero para siempre. Por eso acá se
     * prueba una lista y, si ninguna existe, el comando aborta.
     *
     * @param  string|bool|null  $explicit  Valor de `--asset-base`.
     * @return string|null `null` si ninguna raíz candidata existe.
     */
    private function resolveAssetBase(string|bool|null $explicit, string $tenantSlug): ?string
    {
        $candidates = [];

        if (is_string($explicit) && $explicit !== '') {
            $candidates[] = rtrim($explicit, '/');
        }

        $fromEnv = env('ATLAS_ASSET_BASE');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = rtrim($fromEnv, '/');
        }

        $candidates[] = base_path(path: 'assets');
        $candidates[] = base_path(path: '../assets');

        foreach ($candidates as $candidate) {
            if (is_dir($candidate.'/products/'.$tenantSlug)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Índice `product slug → [brand slugs]` para no repetir un
     * `RecursiveDirectoryIterator` por producto.
     *
     * @return array<string, list<string>>
     */
    private function buildBrandIndex(string $assetBase, string $tenantSlug): array
    {
        $tenantBase = "{$assetBase}/products/{$tenantSlug}";
        if (! is_dir($tenantBase)) {
            return [];
        }

        $index = [];
        foreach (glob($tenantBase.'/*', GLOB_ONLYDIR) ?: [] as $brandDir) {
            $brandSlug = basename($brandDir);
            foreach (glob($brandDir.'/*', GLOB_ONLYDIR) ?: [] as $productDir) {
                $index[basename($productDir)][] = $brandSlug;
            }
        }

        return $index;
    }
}
