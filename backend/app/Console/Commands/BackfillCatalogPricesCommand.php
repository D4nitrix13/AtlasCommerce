<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use FilesystemIterator;
use Generator;
use Illuminate\Console\Command;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Backfill de `products.base_price_cents` y `product_variants.price_cents`
 * desde `data/catalog-meta/{category}/{brand}/{product}[/{variant}]/product.json`.
 *
 * Por qué está en 0 hoy: `CatalogAssetsSeeder::loadBackupIndex()` indexa el
 * backup por el campo `handle` del JSON (`epiphone-masterbilt-texan-faded-cherry`)
 * y `lookupBackupMetadata()` consulta ese índice con el **nombre del
 * directorio del asset** (`masterbilt-texan`). Son dos namespaces distintos,
 * así que el lookup falla y `emptyMetadata()` devuelve `base_price_cents: null`,
 * que el seeder convierte en `?? 0`. De 356 productos sólo 11 —
 * los que por casualidad coinciden con un `handle` — quedaban con precio.
 *
 * Este comando no repite ese error: indexa el backup por **sufijo de path**
 * relativo a la categoría, que es el mismo criterio con el que el árbol de
 * assets está organizado.
 *
 *   - producto de una sola variante → `{brand}/{producto}`
 *   - producto multi-variante       → `{brand}/{producto}/{variante}`
 *
 * Las variantes se resuelven contra el **árbol de assets**, que es la fuente
 * de verdad de qué variante existe, y no contra `finish_slug`: el directorio
 * es lo que nombró el pipeline de imágenes.
 *
 * El precio del producto es el **mínimo** entre sus variantes ("precio desde"),
 * que es la convención de un catálogo con precios por acabado. Para un producto
 * de una sola variante no hay decisión que tomar.
 *
 * Idempotente: sólo escribe cuando el valor calculado difiere del almacenado,
 * así que la segunda corrida reporta `products_updated: 0`.
 *
 * Lo que NO hace:
 *
 *   - No inventa precios. Un producto sin entrada en `catalog-meta` queda como
 *     está y se reporta en `unresolved`, con el motivo.
 *   - No toca precio de venta con descuento ni `compare_at`: eso es otro
 *     problema, con su propio comando.
 *   - No crea productos ni variantes.
 *
 * Uso:
 *   php artisan catalog:backfill-prices --dry-run
 *   php artisan catalog:backfill-prices
 */
final class BackfillCatalogPricesCommand extends Command
{
    protected $signature = 'catalog:backfill-prices
        {--dry-run : Report what would change without writing}
        {--asset-base= : Path to repo assets/ (default: env ATLAS_ASSET_BASE or repo default)}
        {--meta-base= : Path to catalog-meta/ (default: env ATLAS_BACKUP_BASE or repo default)}
        {--product= : Limit to a single product slug}
        {--tenant=instrumentos-musicales-la-voz : Tenant slug}';

    protected $description = 'Backfill product and variant prices from data/catalog-meta/.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option(key: 'dry-run');
        $tenantSlug = (string) $this->option(key: 'tenant');

        $assetBase = $this->resolveAssetBase(explicit: $this->option(key: 'asset-base'), tenantSlug: $tenantSlug);
        if ($assetBase === null) {
            $this->error('No encontré el árbol de assets. Pasá --asset-base=<path> o definí ATLAS_ASSET_BASE.');

            return self::FAILURE;
        }

        $metaBase = $this->resolveMetaBase(explicit: $this->option(key: 'meta-base'));
        if ($metaBase === null) {
            $this->error('No encontré catalog-meta/. Pasá --meta-base=<path> o definí ATLAS_BACKUP_BASE.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            '=== BackfillCatalogPrices (mode=%s, assets=%s, meta=%s) ===',
            $isDryRun ? 'DRY-RUN' : 'WRITE',
            $assetBase,
            $metaBase,
        ));

        $priceIndex = $this->buildPriceIndex(metaBase: $metaBase);
        $brandByProduct = $this->buildBrandIndex(assetBase: $assetBase, tenantSlug: $tenantSlug);

        $this->line(sprintf('Entradas de precio indexadas:  %d', count($priceIndex)));

        $overrides = $this->loadPriceOverrides();
        if ($overrides !== []) {
            $this->line(sprintf('Precios manuales (provisionales): %d  [config/price-overrides.json]', count($overrides)));
        }

        $stats = [
            'products_scanned' => 0,
            'products_updated' => 0,
            'products_unchanged' => 0,
            'variants_updated' => 0,
            'variants_unchanged' => 0,
            'unresolved_no_meta' => 0,
            'unresolved_no_asset_dir' => 0,
            'from_override' => 0,
            'errors' => [],
        ];

        $unresolved = [];

        $this->productQuery(filter: $this->option(key: 'product'))
            ->with(relations: ['variants'])
            ->chunk(count: 100, callback: function ($products) use (
                $assetBase,
                $tenantSlug,
                $brandByProduct,
                $priceIndex,
                $overrides,
                $isDryRun,
                &$stats,
                &$unresolved,
            ): void {
                foreach ($products as $product) {
                    $stats['products_scanned']++;

                    $slug = (string) $product->slug;
                    $brand = $brandByProduct[$slug][0] ?? null;
                    $productDir = $brand === null
                        ? null
                        : "{$assetBase}/products/{$tenantSlug}/{$brand}/{$slug}";

                    if ($productDir === null || ! is_dir($productDir)) {
                        $stats['unresolved_no_asset_dir']++;
                        $unresolved[] = "{$slug}: sin directorio en el árbol de assets";

                        continue;
                    }

                    $variantDirs = glob($productDir.'/*', GLOB_ONLYDIR) ?: [];

                    // Una lista precio→variante por cada variante del disco.
                    // El disco manda: `finish_slug` puede haberse derivado de
                    // otra fuente y no tiene por qué coincidir con el
                    // directorio que nombró el pipeline de imágenes.
                    $priceByVariantDir = [];
                    foreach ($variantDirs as $dir) {
                        $dirSlug = basename($dir);
                        $cents = $this->lookupPrice(
                            index: $priceIndex,
                            brand: $brand,
                            productSlug: $slug,
                            variantSlug: $dirSlug,
                        );

                        if ($cents !== null) {
                            $priceByVariantDir[$dirSlug] = $cents;
                        }
                    }

                    if ($priceByVariantDir === []) {
                        // Sin subdirectorios: precio único a nivel producto.
                        $cents = $this->lookupPrice(index: $priceIndex, brand: $brand, productSlug: $slug, variantSlug: null);

                        $fromOverride = false;
                        if ($cents === null) {
                            $cents = $overrides[$slug] ?? null;
                            $fromOverride = $cents !== null;
                        }

                        if ($cents === null) {
                            $stats['unresolved_no_meta']++;
                            $unresolved[] = "{$slug}: sin entrada en catalog-meta";

                            continue;
                        }

                        if ($fromOverride) {
                            $stats['from_override']++;
                        }

                        $priceByVariantDir['__product__'] = $cents;
                    }

                    $productPrice = min($priceByVariantDir);
                    $variantPrices = $this->pricesForProduct(
                        product: $product,
                        priceByVariantDir: $priceByVariantDir,
                    );

                    $productChanged = (int) $product->base_price_cents !== $productPrice;

                    if ($productChanged) {
                        $stats['products_updated']++;
                    } else {
                        $stats['products_unchanged']++;
                    }

                    if (! $isDryRun && $productChanged) {
                        try {
                            $product->forceFill(attributes: ['base_price_cents' => $productPrice])->save();
                        } catch (Throwable $e) {
                            $stats['errors'][] = "{$slug}: ".$e->getMessage();

                            continue;
                        }
                    }

                    foreach ($variantPrices as [$variant, $cents]) {
                        if ((int) $variant->price_cents !== $cents) {
                            $stats['variants_updated']++;

                            if ($isDryRun) {
                                continue;
                            }

                            try {
                                $variant->forceFill(attributes: ['price_cents' => $cents])->save();
                            } catch (Throwable $e) {
                                $stats['errors'][] = "{$slug}/{$variant->finish_slug}: ".$e->getMessage();
                            }
                        } else {
                            $stats['variants_unchanged']++;
                        }
                    }
                }
            });

        $this->newLine();
        $this->line(sprintf('Productos escaneados:            %d', $stats['products_scanned']));
        $this->line(sprintf('  con precio a cambiar:          %d', $stats['products_updated']));
        $this->line(sprintf('  ya correctos (intactos):      %d', $stats['products_unchanged']));
        $this->line(sprintf('Variantes con precio a cambiar:  %d', $stats['variants_updated']));
        $this->line(sprintf('Variantes ya correctas:         %d', $stats['variants_unchanged']));
        $this->line(sprintf('Sin precio en catalog-meta:      %d', $stats['unresolved_no_meta']));
        $this->line(sprintf('  resueltos con precio manual:   %d', $stats['from_override']));
        $this->line(sprintf('Sin directorio en assets:        %d', $stats['unresolved_no_asset_dir']));

        if ($unresolved !== []) {
            $this->newLine();
            $this->comment(sprintf('Muestra de productos sin precio resoluble (%d en total):', count($unresolved)));
            foreach (array_slice($unresolved, 0, 20) as $line) {
                $this->line('  - '.$line);
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
     * Devuelve el precio en centavos del producto, y por variante cuando el
     * producto es multi-acabado.
     *
     * El precio del producto es el mínimo: en un catálogo con precios por
     * acabado, el precio del producto es el "desde".
     *
     * @param  array<string, array{product_dir: string|null, variant_dir: string|null, cents: int|null}>  $priceByVariantDir
     * @return Generator<int, array{ProductVariant, int}>
     */
    private function pricesForProduct(Product $product, array $priceByVariantDir): Generator
    {
        if (isset($priceByVariantDir['__product__'])) {
            $cents = $priceByVariantDir['__product__'];

            foreach ($product->variants as $variant) {
                yield [$variant, $cents];
            }

            return;
        }

        foreach ($priceByVariantDir as $dirSlug => $cents) {
            $variant = $product->variants->first(
                static fn (ProductVariant $v): bool => (string) $v->finish_slug === $dirSlug,
            );

            // Producto de una sola variante en disco pero con la fila de BD
            // partido en varias: no hay forma honesta de decidir a cuál
            // pertenece el precio, así que no se asigna a ninguna.
            if ($variant === null) {
                continue;
            }

            yield [$variant, $cents];
        }
    }

    /**
     * Busca el precio por clave de path.
     *
     * Las claves candidatas, en orden:
     *   1. `{brand}/{producto}/{variante}` — multi-acabado.
     *   2. `{brand}/{producto}` — un solo precio a nivel producto.
     *   3. Sin prefijo de marca. Un puñado de productos del árbol llevan la
     *      marca pegada al slug (`gibson-mod-collection-es-335-smooth-sepia`)
     *      y `catalog-meta` no. Sin este fallback quedan en 0.
     */
    /**
     * Precios manuales de `data/price-overrides.json`, en centavos.
     *
     * Existen para los productos que el backup no cubre — el Mod Collection
     * de Gibson, por ejemplo, no está en `catalog-meta` — y son
     * PROVISIONALES hasta que haya una lista real. Se aplican sólo cuando
     * `catalog-meta` no tiene entrada, así que un precio real siempre gana.
     *
     * @return array<string, int>
     */
    private function loadPriceOverrides(): array
    {
        $path = $this->resolveOverridesFile();

        if ($path === null) {
            return [];
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return [];
        }

        try {
            $data = json_decode($bytes, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error("No pude leer {$path}: JSON inválido. Sigo sin overrides.");

            return [];
        }

        $prices = $data['prices'] ?? [];

        return array_map(
            static fn (mixed $dollars): int => (int) round((float) $dollars * 100),
            array_filter($prices, is_numeric(...)),
        );
    }

    /**
     * `data/` vive en la raíz del repo, no en `backend/`, así que su
     * posición relativa cambia según dónde se ejecute: en el host es
     * `../data`, en el container es `data`. Probar una sola deja el archivo
     * sin leer en uno de los dos entornos, en silencio.
     */
    private function resolveOverridesFile(): ?string
    {
        $path = base_path(path: 'config/price-overrides.json');

        return is_file($path) ? $path : null;
    }

    /**
     * @param  array<string, int>  $index
     */
    private function lookupPrice(array $index, string $brand, string $productSlug, ?string $variantSlug): ?int
    {
        $keys = [];

        if ($variantSlug !== null) {
            $keys[] = "{$brand}/{$productSlug}/{$variantSlug}";
        }

        $keys[] = "{$brand}/{$productSlug}";

        $bareSlug = $this->stripBrandPrefix(slug: $productSlug, brand: $brand);
        if ($bareSlug !== $productSlug) {
            if ($variantSlug !== null) {
                $keys[] = "{$brand}/{$bareSlug}/{$variantSlug}";
            }

            $keys[] = "{$brand}/{$bareSlug}";
        }

        foreach ($keys as $key) {
            if (isset($index[$key])) {
                return $index[$key];
            }
        }

        return null;
    }

    /**
     * `catalog-meta` indexa por la marca en un segmento propio; el árbol de
     * assets a veces la duplica dentro del slug del producto. Normalizarlo acá
     * es preferible a inventar una excepción por producto.
     */
    private function stripBrandPrefix(string $slug, string $brand): string
    {
        $prefix = $brand.'-';

        return str_starts_with($slug, $prefix) ? substr($slug, strlen($prefix)) : $slug;
    }

    /**
     * Índice `sufijo de path → centavos`.
     *
     * La categoría (`guitars`, `amps/heads`, `cases/soft`) queda fuera a
     * propósito: el árbol de assets no la tiene, así que si fuera parte de la
     * clave no habría join posible.
     *
     * @return array<string, int>
     */
    private function buildPriceIndex(string $metaBase): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($metaBase, FilesystemIterator::SKIP_DOTS),
        );

        $index = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getFilename() !== 'product.json') {
                continue;
            }

            $bytes = @file_get_contents($file->getPathname());
            if ($bytes === false) {
                continue;
            }

            try {
                $data = json_decode($bytes, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            $cents = $this->centsFromPriceArray(price: $data['price'] ?? null);
            if ($cents === null) {
                continue;
            }

            $segments = explode('/', trim(str_replace($metaBase, '', $file->getPath()), '/'));
            $segments = array_values(array_filter($segments, static fn (string $s): bool => $s !== ''));

            // Se indexan las dos profundidades porque las categorías del backup
            // están anidadas (`parts/pickups/`, `amps/heads/`, `cases/soft/`):
            // el conteo de segmentos NO dice si una entrada es de producto o
            // de variante. `{marca}/{producto}` y `{marca}/{producto}/{variante}`
            // se consultan con reglas distintas según el caso, así que
            // guardar las dos es lo correcto: guardar una sola deja sin
            // precio a los productos cuya categoría tiene más de un nivel.
            // El `??=` hace que, cuando dos entradas colisionen, gane la
            // primera.
            foreach ([2, 3] as $depth) {
                if (count($segments) < $depth) {
                    continue;
                }

                $index[implode('/', array_slice($segments, -$depth))] ??= $cents;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>|null  $price
     */
    private function centsFromPriceArray(?array $price): ?int
    {
        $regular = $price['regular'] ?? null;

        if (! is_numeric($regular) || (float) $regular <= 0) {
            return null;
        }

        return (int) round((float) $regular * 100);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private function productQuery(string|bool|null $filter): \Illuminate\Database\Eloquent\Builder
    {
        $query = Product::query();

        if (is_string($filter) && $filter !== '') {
            $query->where(column: 'slug', operator: '=', value: $filter);
        }

        return $query;
    }

    /**
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
            foreach (glob($brandDir.'/*', GLOB_ONLYDIR) ?: [] as $productDir) {
                $index[basename($productDir)][] = basename($brandDir);
            }
        }

        return $index;
    }

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
        $candidates[] = base_path(path: '../../assets');

        foreach ($candidates as $candidate) {
            if (is_dir($candidate.'/products/'.$tenantSlug)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveMetaBase(string|bool|null $explicit): ?string
    {
        $candidates = [];

        if (is_string($explicit) && $explicit !== '') {
            $candidates[] = rtrim($explicit, '/');
        }

        $fromEnv = env('ATLAS_BACKUP_BASE');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = rtrim($fromEnv, '/');
        }

        $candidates[] = base_path(path: '../data/catalog-meta');
        $candidates[] = base_path(path: 'data/catalog-meta');

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
