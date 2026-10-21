<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Services\Assets\AssetBaseResolver;
use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rellena `product_images.variant_id` y `product_images.width` desde el
 * árbol de assets en disco.
 *
 * Contexto: el seeder `assets:seed-catalog` itera
 * el árbol `assets/products/{tenant}/{brand}/{product}/{variant}/` y guarda
 * cada `product_images` con un `image_url` derivado del SHA-256 del binario
 * (`StorageKeyDeriver`). El slug del variant se conoce en seed-time, pero
 * el seeder histórico NO lo persistía, así que la PDP no podía filtrar la
 * galería por acabado y mostraba todas las imágenes mezcladas.
 *
 * `width` es el mismo problema con otro síntoma: las 4 resoluciones de una
 * foto (`front-400/800/1200/1600`) son filas indistinguibles en BD —el
 * `storage_key` es un hash del binario, no un nombre— y la galería las
 * contaba como 4 fotos distintas.
 *
 * Ambos datos salen del MISMO recorrido: un único walk del árbol, un único
 * `sha256` por webp. Separarlos en dos comandos significaría hashear dos
 * veces cada archivo del catálogo.
 *
 * Compatibilidad:
 *
 *   - Productos multi-variant CON árbol de variantes en disco (15/19 en
 *     el diagnóstico de Oct 2026): cada webp se ata al variant de su
 *     subdirectorio. Cobertura completa.
 *   - Productos single-variant CON assets planos en product root (150 en
 *     el diagnóstico): todas las imágenes quedan atadas al único variant.
 *   - Productos SIN asset dir en disco (4 multi-variant + 169
 *     single-variant): no se tocan, quedan con `variant_id = NULL` y la
 *     PDP degrada a mostrar el set completo (correcto).
 *   - Imágenes en BD sin contraparte en el árbol: cuentan como `orphans`
 *     y no se modifican.
 *
 * Idempotente: cada columna se escribe sólo cuando está vacía, así que
 * re-correrlo reporta 0. `--force` reescribe las que ya tienen valor.
 *
 * --dry-run: recalcula y reporta sin escribir. --force: reescribe filas
 * ya seteadas. --tenant=: filtra a un tenant específico.
 */
final class BackfillImageVariantCommand extends Command
{
    protected $signature = 'assets:backfill-image-variant
        {--tenant=instrumentos-musicales-la-voz : Tenant slug}
        {--asset-base= : Path to repo assets/ (default: env ATLAS_ASSET_BASE or repo default)}
        {--dry-run : Recalculate and report without writing to DB}
        {--force : Overwrite existing variant_id/width on rows that already have one}';

    protected $description = 'Backfill product_images.variant_id and .width by hashing on-disk webps and matching storage keys.';

    public function __construct(
        private readonly StorageKeyDeriver $deriver,
        private readonly AssetFilenameParser $filenames,
    ) {
        parent::__construct();
    }

    public function handle(AssetBaseResolver $assetBases): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $isForce = (bool) $this->option(key: 'force');
        $tenant = (string) $this->option(key: 'tenant');

        // Un default fijo (`base_path('../../assets')`) resuelve a
        // `/var/assets` dentro del container, que no existe — y un path
        // inexistente no lanza error: los 346 productos contaban como
        // "sin assets en disco" en vez de decir que no se encontró la
        // raíz. Abortar es la única salida honesta acá.
        $assetBase = $assetBases->resolve(
            explicit: $this->resolveExplicitAssetBase(),
            tenantSlug: $tenant,
        );

        if ($assetBase === null) {
            $this->error(
                "No encontré el árbol de assets del tenant '{$tenant}'. "
                .'Pasá --asset-base=<path> o definí ATLAS_ASSET_BASE. '
                .'Sin esa ruta el backfill no puede re-derivar los storage_key '
                .'y no debe seguir: reportaría ceroes sin decir por qué.',
            );

            return self::FAILURE;
        }

        $this->info(string: sprintf(
            '=== BackfillImageVariant (tenant=%s, asset-base=%s, mode=%s) ===',
            $tenant,
            $assetBase,
            $isDryRun ? 'DRY-RUN' : ($isForce ? 'WRITE-FORCE' : 'WRITE'),
        ));

        $tenantStoreId = DB::table(table: 'stores')
            ->where(column: 'slug', operator: '=', value: $tenant)
            ->value(column: 'id');

        if ($tenantStoreId === null) {
            $this->error(string: "Store con slug='{$tenant}' no existe.");

            return self::FAILURE;
        }

        $productIds = DB::table(table: 'product_stores')
            ->where(column: 'store_id', operator: '=', value: $tenantStoreId)
            ->pluck(column: 'product_id')
            ->all();

        $products = Product::query()
            ->whereIn(column: 'id', values: $productIds)
            ->with(relations: ['brand', 'variants', 'images'])
            ->get();

        $this->line(string: sprintf('Productos del tenant: %d', $products->count()));

        $stats = [
            'products_processed' => 0,
            'images_attached' => 0,
            'images_width_filled' => 0,
            'images_skipped' => 0,
            'images_orphans' => 0,
            'images_missing_asset_dir' => 0,
            'errors' => [],
        ];

        foreach ($products as $product) {
            try {
                $this->backfillOneProduct(
                    product: $product,
                    tenantSlug: $tenant,
                    assetBase: $assetBase,
                    isDryRun: $isDryRun,
                    isForce: $isForce,
                    stats: $stats,
                );
                $stats['products_processed']++;
            } catch (Throwable $e) {
                $stats['errors'][] = "Product id={$product->id} ({$product->slug}): ".$e->getMessage();
            }
        }

        $this->newLine();
        $this->info('=== Resumen ===');
        foreach ($stats as $key => $value) {
            if ($key === 'errors') {
                continue;
            }
            $this->line(string: "  {$key}: {$value}");
        }
        if ($stats['errors'] !== []) {
            $this->error('Errores:');
            foreach ($stats['errors'] as $err) {
                $this->line(string: "  ❌ {$err}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Backfill de un producto individual.
     *
     * Estrategia de resolución:
     *
     *   1. Localizar el directorio del producto en el árbol de assets
     *      buscando por nombre en todos los brand dirs (mismo método
     *      que `CatalogAssetsSeeder::discoverFromFilesystem`).
     *   2. Construir un mapa `storage_key → {variant_slug, width}` walkeando
     *      el contenido.
     *   3. Para cada `product_images` de este producto, matchear por
     *      `image_url` y setear `variant_id` y `width`.
     *   4. Productos sin asset dir en absoluto: se cuentan y se saltean.
     *
     * @param  array{
     *     products_processed: int,
     *     images_attached: int,
     *     images_width_filled: int,
     *     images_skipped: int,
     *     images_orphans: int,
     *     images_missing_asset_dir: int,
     *     errors: array<int, string>,
     * }  $stats  Contadores por referencia.
     */
    private function backfillOneProduct(
        Product $product,
        string $tenantSlug,
        string $assetBase,
        bool $isDryRun,
        bool $isForce,
        array &$stats,
    ): void {
        $productRoot = $this->locateProductAssetDir(
            assetBase: $assetBase,
            tenantSlug: $tenantSlug,
            productSlug: $product->slug,
        );

        if ($productRoot === null) {
            $stats['images_missing_asset_dir']++;

            return;
        }

        $variantByWebp = [];
        foreach ($this->discoverProductWebps(productRoot: $productRoot) as $entry) {
            $bytes = @file_get_contents(filename: $entry['path']);
            if ($bytes === false) {
                continue;
            }
            try {
                $key = $this->deriver->derive(
                    tenantSlug: $tenantSlug,
                    collection: 'products',
                    entity: $product->slug,
                    content: $bytes,
                    extension: 'webp',
                );
            } catch (Throwable) {
                continue;
            }
            // Si el mismo binario aparece dos veces en el árbol (caso
            // raro), gana el primer variant encontrado — preserva el
            // comportamiento determinístico del seeder.
            $variantByWebp[$key] ??= [
                'variant_slug' => $entry['variant_slug'] ?? '',
                'width' => $entry['width'],
            ];
        }

        // Variantes del producto en BD, indexadas por finish_slug
        $variantsBySlug = [];
        foreach ($product->variants as $variant) {
            if ($variant->finish_slug !== null && $variant->finish_slug !== '') {
                $variantsBySlug[$variant->finish_slug] = $variant;
            }
        }

        foreach ($product->images as $image) {
            $matched = $variantByWebp[$image->image_url] ?? null;

            if ($matched === null) {
                // Sin match en disco: cuenta como huérfana. La PDP
                // degradará limpio (mostrará todas las imágenes).
                $stats['images_orphans']++;

                continue;
            }

            $isDirty = false;

            // ── variant_id ──────────────────────────────────────────
            $variant = $variantsBySlug[$matched['variant_slug']] ?? null;
            if ($variant === null) {
                $stats['images_orphans']++;
            } elseif ($isForce || $image->variant_id === null) {
                if (! $isDryRun) {
                    $image->variant_id = $variant->id;
                }
                $stats['images_attached']++;
                $isDirty = true;
            }

            // ── width ───────────────────────────────────────────────
            // El slug del variant puede no existir en BD y el width
            // seguir siendo recuperable: son columnas independientes y
            // una galería de UNA foto no depende de ninguna de las dos.
            if ($matched['width'] !== null && ($isForce || $image->width === null)) {
                if (! $isDryRun) {
                    $image->width = $matched['width'];
                }
                $stats['images_width_filled']++;
                $isDirty = true;
            }

            if ($isDirty && ! $isDryRun) {
                $image->save();
            }

            if (! $isDirty) {
                $stats['images_skipped']++;
            }
        }
    }

    /**
     * Override explícito de la raíz de assets: `--asset-base` primero,
     * después `ATLAS_ASSET_BASE`.
     *
     * Leer el env vive acá y no en `AssetBaseResolver` porque `env()`
     * fuera de `config/` está prohibido por Larastan — la capa CLI es la
     * que puede tocar variables de entorno.
     */
    private function resolveExplicitAssetBase(): ?string
    {
        $fromOption = $this->option(key: 'asset-base');
        if (is_string($fromOption) && $fromOption !== '') {
            return $fromOption;
        }

        $fromEnv = env(key: 'ATLAS_ASSET_BASE');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : null;
    }

    /**
     * Localiza el directorio del producto en el árbol de assets buscando
     * por nombre bajo
     * `assets/products/<tenant>/<cualquier-brand>/<productSlug>/`.
     *
     * Devuelve `null` si el producto no tiene assets en el árbol (caso
     * 4/19 multi-variant y 169/346 single-variant según diagnóstico
     * Oct 2026).
     */
    private function locateProductAssetDir(
        string $assetBase,
        string $tenantSlug,
        string $productSlug,
    ): ?string {
        $tenantBase = rtrim(string: $assetBase, characters: '/').'/products/'.$tenantSlug;
        if (! is_dir(filename: $tenantBase)) {
            return null;
        }
        $brandDirs = glob(pattern: $tenantBase.'/*', flags: GLOB_ONLYDIR) ?: [];
        foreach ($brandDirs as $brandDir) {
            $candidate = $brandDir.'/'.$productSlug;
            if (is_dir(filename: $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Walkea el árbol local del producto y devuelve cada webp encontrado
     * con su variant_slug y su ancho, inferidos desde el path.
     *
     * Replica la lógica de `CatalogAssetsSeeder::discoverFromFilesystem`:
     * cuando no hay subdirectorios de variante pero sí webps en el root
     * del producto, los trata como un único "variant dir" cuyo slug es
     * el basename del product root — así el match con `finish_slug` en
     * BD funciona sin ambigüedad.
     *
     * El ancho sale de `AssetFilenameParser`, el MISMO parseo que usa el
     * seeder al escribir la fila: si los dos paths se derivaran del regex
     * por separado, una corrección en uno dejaría al otro escribiendo
     * anchos desplazados.
     *
     * @return iterable<array{variant_slug: string, width: int|null, path: string}>
     */
    private function discoverProductWebps(string $productRoot): iterable
    {
        $variantDirs = glob(pattern: $productRoot.'/*', flags: GLOB_ONLYDIR) ?: [];
        $flatWebps = glob(pattern: $productRoot.'/*-*.webp') ?: [];

        if ($variantDirs === [] && $flatWebps !== []) {
            // Caso single-variant (o seeder que no usó subdirectorios).
            // Mismo trato que el seeder: variant_slug = product.slug.
            $pseudoVariantSlug = basename(path: $productRoot);
            foreach ($flatWebps as $webp) {
                yield [
                    'variant_slug' => $pseudoVariantSlug,
                    'width' => $this->widthOf(path: $webp),
                    'path' => $webp,
                ];
            }

            return;
        }

        foreach ($variantDirs as $variantDir) {
            $variantSlug = basename(path: $variantDir);
            $webps = glob(pattern: $variantDir.'/*-*.webp') ?: [];
            foreach ($webps as $webp) {
                yield [
                    'variant_slug' => $variantSlug,
                    'width' => $this->widthOf(path: $webp),
                    'path' => $webp,
                ];
            }
        }
    }

    /**
     * Ancho del webp según su nombre, o `null` si el nombre no sigue la
     * convención `{role}-{WxH}.webp`.
     *
     * Un archivo que no matchea deja la fila con `width = NULL`, que el
     * Resource interpreta como "no se sabe" — no como "la resolución más
     * baja".
     */
    private function widthOf(string $path): ?int
    {
        $parsed = $this->filenames->parseRoleAndSize(base: basename(path: $path, suffix: '.webp'));

        return $parsed === null ? null : (int) $parsed['size_w'];
    }
}
