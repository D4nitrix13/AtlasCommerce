<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Services\Assets\StorageKeyDeriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rellena `product_images.variant_id` desde el árbol de assets en disco.
 *
 * Contexto: el seeder `assets:seed-catalog` itera
 * el árbol `assets/products/{tenant}/{brand}/{product}/{variant}/` y guarda
 * cada `product_images` con un `image_url` derivado del SHA-256 del binario
 * (`StorageKeyDeriver`). El slug del variant se conoce en seed-time, pero
 * NO se persiste en `product_images`, así que la PDP no puede filtrar la
 * galería por acabado y muestra todas las imágenes mezcladas.
 *
 * Este backfill walkea el filesystem con el MISMO orden que el seeder,
 * re-deriva el `storage_key` de cada webp (mismo algoritmo de hash, así
 * que matchea por contenido) y resuelve la fila de `product_images`
 * correspondiente para setear `variant_id`.
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
 * Idempotente: re-correrlo no duplica asociaciones. Si `variant_id` ya
 * está seteado, salta la fila (a menos que se pase `--force`).
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
        {--force : Overwrite existing variant_id on rows that already have one}';

    protected $description = 'Backfill product_images.variant_id by hashing on-disk webps and matching storage keys.';

    public function __construct(private readonly StorageKeyDeriver $deriver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $isForce = (bool) $this->option(key: 'force');
        $tenant = (string) $this->option(key: 'tenant');
        $assetBase = (string) ($this->option(key: 'asset-base')
            ?: env('ATLAS_ASSET_BASE', base_path(path: '../../assets')));

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
     *   2. Construir un mapa `storage_key → variant_slug` walkeando el
     *      contenido.
     *   3. Para cada `product_images` de este producto, matchear por
     *      `image_url` y setear `variant_id`.
     *   4. Productos sin asset dir en absoluto: se cuentan y se saltean.
     *
     * @param  array{
     *     products_processed: int,
     *     images_attached: int,
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
        foreach ($this->discoverProductWebps($productRoot) as $entry) {
            $variantSlug = $entry['variant_slug'] ?? '';
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
            if (! isset($variantByWebp[$variantSlug])) {
                $variantByWebp[$variantSlug] = [];
            }
            $variantByWebp[$variantSlug][$key] = true;
        }

        // Aplana: storage_key → variant_slug
        $storageKeyToVariant = [];
        foreach ($variantByWebp as $variantSlug => $keys) {
            foreach (array_keys(array: $keys) as $key) {
                $storageKeyToVariant[$key] = $variantSlug;
            }
        }

        // Variantes del producto en BD, indexadas por finish_slug
        $variantsBySlug = [];
        foreach ($product->variants as $variant) {
            if ($variant->finish_slug !== null && $variant->finish_slug !== '') {
                $variantsBySlug[$variant->finish_slug] = $variant;
            }
        }

        foreach ($product->images as $image) {
            $matchedVariantSlug = $storageKeyToVariant[$image->image_url] ?? null;

            if ($matchedVariantSlug === null || ! isset($variantsBySlug[$matchedVariantSlug])) {
                // Sin match en disco o el slug no existe como variant en
                // BD: cuentan como huérfanos. La PDP degradará limpio
                // (mostrará todas las imágenes del producto).
                $stats['images_orphans']++;

                continue;
            }

            $variant = $variantsBySlug[$matchedVariantSlug];

            if (! $isForce && $image->variant_id !== null) {
                $stats['images_skipped']++;

                continue;
            }

            if (! $isDryRun) {
                $image->variant_id = $variant->id;
                $image->save();
            }
            $stats['images_attached']++;
        }
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
     * con su variant_slug inferido desde el path.
     *
     * Replica la lógica de `CatalogAssetsSeeder::discoverFromFilesystem`:
     * cuando no hay subdirectorios de variante pero sí webps en el root
     * del producto, los trata como un único "variant dir" cuyo slug es
     * el basename del product root — así el match con `finish_slug` en
     * BD funciona sin ambigüedad.
     *
     * @return iterable<array{variant_slug: string, path: string}>
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
                yield ['variant_slug' => $pseudoVariantSlug, 'path' => $webp];
            }

            return;
        }

        foreach ($variantDirs as $variantDir) {
            $variantSlug = basename(path: $variantDir);
            $webps = glob(pattern: $variantDir.'/*-*.webp') ?: [];
            foreach ($webps as $webp) {
                yield ['variant_slug' => $variantSlug, 'path' => $webp];
            }
        }
    }
}
