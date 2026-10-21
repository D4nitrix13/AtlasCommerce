<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;

/**
 * Audita si `product_images` es un espejo fiel del árbol de assets.
 *
 * ── El criterio ──────────────────────────────────────────────────────
 *
 * El `storage_key` es `sha256(binario)` con prefijo de 12 chars y el
 * path de storage NO lleva variante:
 *
 *     tenants/{tenant}/products/{product-slug}/{hash12}.webp
 *
 * Eso hace que dos archivos con el mismo contenido produzcan EXACTAMENTE
 * la misma key. Es la propiedad que permite detectar, sin heurísticas ni
 * visión por computadora, dos anomalías:
 *
 *   1. Colisión de roles: el mismo binario aparece con dos nombres de
 *      rol distintos (`front` en un acabado, `headstock` en otro). Es
 *      el signal de "el front está mal nombrado" — pero OJO: sólo
 *      cuando los dos roles están en carpetas de variante DISTINTAS.
 *      Dentro de una misma carpeta nunca hay dos binarios idénticos, así
 *      que un duplicate dentro de la variante no es detectable por hash
 *      (y no ocurre: 0 de 10.249 archivos del catálogo).
 *   2. Colisión de foto compartida: el mismo binario aparece con el
 *      MISMO rol en dos variantes (el left-handed reusando las fotos
 *      del diestro). No es un error de naming, pero sí una pérdida de
 *      fila en BD.
 *
 * En los dos casos la BD tiene UNA fila donde el árbol tiene VARIAS
 *MENSAJES: el resto de los reclamos del archivo quedan sin fila.
 *
 * ── Lo que NO puede detectar ──────────────────────────────────────────
 *
 * Un archivo cuyo CONTENIDO no corresponde a su nombre (`front` que en
 * realidad es un primer plano del clavijero) es indistinguible por hash:
 * el binario es único y la fila que lo apunta dice exactamente lo que
 * el archivo dice. Detectarlo requiere revisar las fotos, no la base.
 *
 * ── Reparación ───────────────────────────────────────────────────────
 *
 * `repair()` sólo INSERTA filas. Nunca borra, nunca modifica una fila
 * existente y nunca toca el árbol de assets: replica el archivo a la BD,
 * que es un espejo. Es idempotente — la existence check es
 * (product_id, variant_id, image_url).
 */
final class GalleryRoleAuditor
{
    public function __construct(
        private readonly StorageKeyDeriver $deriver,
        private readonly AssetFilenameParser $filenames,
        private readonly string $assetBase,
        private readonly string $tenantSlug,
    ) {}

    /**
     * Compara el árbol de assets contra `product_images`.
     *
     * @return array{
     *     files_on_disk: int,
     *     parseable_files: int,
     *     products_on_disk: int,
     *     unparseable: array<int, array{path: string, product: string, variant: string}>,
     *     role_conflicts: array<int, array{product: string, storage_key: string, width: int, claims: array<int, array{variant: string, role: string}>}>,
     *     shared_photos: array<int, array{product: string, storage_key: string, width: int, claims: array<int, array{variant: string, role: string}>}>,
     *     missing_rows: array<int, array{product: string, product_id: int, variant: string, variant_id: int|null, role: string, width: int, storage_key: string}>,
     *     variants_without_primary: array<int, array{product: string, variant: string, variant_id: int|null}>,
     *     products_without_db_row: array<int, array{product: string, claims: int}>,
     * }
     */
    public function audit(): array
    {
        $report = [
            'files_on_disk' => 0,
            'parseable_files' => 0,
            'products_on_disk' => 0,
            'unparseable' => [],
            'role_conflicts' => [],
            'shared_photos' => [],
            'missing_rows' => [],
            'variants_without_primary' => [],
            'products_without_db_row' => [],
        ];

        /** Claims por storage_key: qué variante y rol piden ese binario. */
        $claimsByKey = [];
        $productsOnDisk = [];

        foreach ($this->variantFolders() as [$productSlug, $variantSlug, $folderPath]) {
            $productsOnDisk[$productSlug] = true;

            foreach (glob($folderPath.'/*-*.webp') ?: [] as $webpPath) {
                $report['files_on_disk']++;

                $base = basename($webpPath, suffix: '.webp');
                $parsed = $this->filenames->parseRoleAndSize(base: $base);
                if ($parsed === null) {
                    // El seeder los descarta en silencio (`continue`): el
                    // producto queda sin galería. Renombrarlos es
                    // intervención manual sobre assets, no una reparación
                    // de BD, así que acá sólo se reportan.
                    $report['unparseable'][] = [
                        'path' => $webpPath,
                        'product' => $productSlug,
                        'variant' => $variantSlug,
                    ];

                    continue;
                }

                $report['parseable_files']++;
                $storageKey = $this->storageKeyFor(webpPath: $webpPath, productSlug: $productSlug);
                $claimsByKey[$storageKey][] = [
                    'product' => $productSlug,
                    'variant' => $variantSlug,
                    'role' => $parsed['role'],
                    'width' => (int) $parsed['size_w'],
                    'storage_key' => $storageKey,
                ];
            }
        }

        $report['products_on_disk'] = count($productsOnDisk);

        $this->classifyCollisions(claimsByKey: $claimsByKey, report: $report);
        $this->diffAgainstDatabase(claimsByKey: $claimsByKey, report: $report);

        return $report;
    }

    /**
     * Inserta las filas que le faltan a `product_images`.
     *
     * Sólo agrega filas que el árbol reclama y la BD no tiene. Idempotente:
     * correrlo dos veces inserta lo mismo la primera vez y nada la segunda.
     *
     * @param  array<string, mixed>  $report  Salida de `audit()`.
     * @return array{inserted: int, skipped: int}
     */
    public function repair(array $report): array
    {
        $inserted = 0;
        $skipped = 0;

        foreach ($report['missing_rows'] as $missing) {
            $variantId = $missing['variant_id'];

            if ($variantId === null) {
                // Sin variant no hay a qué colgar la fila: el producto
                // existe pero la variante no se puede resolver (slug de
                // acabado distinto al directorio). Requiere decisión
                // humana, no un INSERT.
                $skipped++;

                continue;
            }

            $exists = ProductImage::query()
                ->where(column: 'product_id', operator: '=', value: $missing['product_id'])
                ->where(column: 'variant_id', operator: '=', value: $variantId)
                ->where(column: 'image_url', operator: '=', value: $missing['storage_key'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $variant = ProductVariant::query()->find(id: $variantId);
            $product = Product::query()->find(id: $missing['product_id']);

            if ($variant === null || $product === null) {
                $skipped++;

                continue;
            }

            // Primary sólo si la variante no tiene ninguna: la regla del
            // seeder es "1 primary por variante", y un finish/1600 perdido
            // es el único caso donde se puede recuperar sin pisar la
            // elección de otro acabado.
            $variantHasPrimary = ProductImage::query()
                ->where(column: 'variant_id', operator: '=', value: $variantId)
                ->where(column: 'is_primary', operator: '=', value: true)
                ->exists();

            $isPrimary = $missing['role'] === 'front'
                && $missing['width'] === 1600
                && ! $variantHasPrimary;

            ProductImage::query()->create(attributes: [
                'product_id' => $missing['product_id'],
                'variant_id' => $variantId,
                'image_url' => $missing['storage_key'],
                'alt_text' => "{$product->name} - vista {$missing['role']}",
                'is_primary' => $isPrimary,
                'sort_order' => CatalogAssetsSeeder::ROLE_ORDER[$missing['role']] ?? 99,
                'width' => $missing['width'],
            ]);

            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Carpetas de variante del tenant, replicando la convención del
     * seeder: `assets/products/{tenant}/{brand}/{product}/{variant}/`, con
     * el caso single-variant donde los webp cuelgan del directorio del
     * producto y la "variante" es el propio slug del producto.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function variantFolders(): array
    {
        $tenantBase = rtrim(string: $this->assetBase, characters: '/').'/products/'.$this->tenantSlug;
        if (! is_dir(filename: $tenantBase)) {
            return [];
        }

        $folders = [];
        foreach (glob($tenantBase.'/*', GLOB_ONLYDIR) ?: [] as $brandDir) {
            foreach (glob($brandDir.'/*', GLOB_ONLYDIR) ?: [] as $productDir) {
                $productSlug = basename($productDir);
                $variantDirs = glob($productDir.'/*', GLOB_ONLYDIR) ?: [];

                if ($variantDirs === [] && glob($productDir.'/*-*.webp') !== []) {
                    $variantDirs = [$productDir];
                }

                foreach ($variantDirs as $variantDir) {
                    $folders[] = [$productSlug, basename($variantDir), $variantDir];
                }
            }
        }

        return $folders;
    }

    /**
     * El `entity` de la clave de storage es el slug del PRODUCTO, y lo
     * aporta `variantFolders()` — que ya lo resolvió del árbol — en vez
     * de recalcularlo desde el path.
     *
     * Recalcularlo con offsets no funciona: el layout tiene dos formas
     * (`{brand}/{product}/{variant}/{file}` y, en productos de una sola
     * variante, `{brand}/{product}/{file}`), así que cualquier offset
     * fijo acierta en una y cae en la MARCA en la otra. El seeder siempre
     * usa `$product->slug`, así que un `entity` de marca produce una
     * clave que no existe en la BD y el diff marca como faltante una
     * fila que sí está.
     */
    private function storageKeyFor(string $webpPath, string $productSlug): string
    {
        return $this->deriver->derive(
            tenantSlug: $this->tenantSlug,
            collection: 'products',
            entity: $productSlug,
            content: (string) file_get_contents(filename: $webpPath),
            extension: 'webp',
        );
    }

    /**
     * Separa las colisiones en "roles en conflicto" (el binario sirve para
     * dos roles distintos → nombre sospechoso) de "foto compartida" (el
     * mismo rol en dos variantes → decisión de catálogo, no de naming).
     *
     * @param  array<string, array<int, array<string, mixed>>>  $claimsByKey
     * @param  array<string, mixed>  $report
     */
    private function classifyCollisions(array $claimsByKey, array &$report): void
    {
        foreach ($claimsByKey as $claims) {
            $variants = array_unique(array_column($claims, 'variant'));
            if (count($variants) < 2) {
                continue;
            }

            $roles = array_unique(array_column($claims, 'role'));
            $entry = [
                'product' => $claims[0]['product'],
                'storage_key' => $claims[0]['storage_key'],
                'width' => $claims[0]['width'],
                'claims' => array_map(
                    static fn (array $c): array => ['variant' => $c['variant'], 'role' => $c['role']],
                    $claims,
                ),
            ];

            $report[count($roles) > 1 ? 'role_conflicts' : 'shared_photos'][] = $entry;
        }
    }

    /**
     * Compara los reclamos del árbol contra las filas de `product_images`.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $claimsByKey
     * @param  array<string, mixed>  $report
     */
    private function diffAgainstDatabase(array $claimsByKey, array &$report): void
    {
        $variantsWithoutPrimary = [];
        $productsWithoutDbRow = [];

        /**
         * El chequeo de primary es por VARIANTE, no por binario: correrlo
         * una vez por cada `claimsByKey` lo repetía por cada clave de
         * storage del producto. Con 9.836 filas eso multiplicaba las 11
         * variantes reales sin primary por ~19 y el reporte imprimía 208
         * líneas. Se marca el producto ya revisado y se sigue.
         *
         * @var array<int, true> $productsChecked
         */
        $productsChecked = [];

        foreach ($claimsByKey as $claims) {
            $productSlug = $claims[0]['product'];
            $product = Product::query()->where(column: 'slug', operator: '=', value: $productSlug)->first();

            if ($product === null) {
                // Sin fila en `products` no hay a qué colgar nada. Antes
                // este `continue` descartaba el directorio entero en
                // silencio: el producto desaparecía del reporte y sus
                // archivos no contaban ni como faltantes ni como ok. Es
                // un gap que ninguna reparación de BD cierra — hay que
                // decidir si el producto se importa — pero callarlo lo
                // vuelve invisible.
                $productsWithoutDbRow[$productSlug] = ($productsWithoutDbRow[$productSlug] ?? 0) + count($claims);

                continue;
            }

            $existing = ProductImage::query()
                ->where(column: 'product_id', operator: '=', value: $product->id)
                ->get(['variant_id', 'image_url', 'is_primary'])
                ->keyBy(fn (ProductImage $image): string => $image->variant_id.'|'.$image->image_url);

            foreach ($claims as $claim) {
                $variant = $this->resolveVariant(product: $product, variantSlug: $claim['variant']);

                if ($variant === null) {
                    $report['missing_rows'][] = [
                        'product' => $productSlug,
                        'product_id' => (int) $product->id,
                        'variant' => $claim['variant'],
                        'variant_id' => null,
                        'role' => $claim['role'],
                        'width' => $claim['width'],
                        'storage_key' => $claim['storage_key'],
                    ];

                    continue;
                }

                $key = $variant->id.'|'.$claim['storage_key'];
                if ($existing->has($key)) {
                    continue;
                }

                $report['missing_rows'][] = [
                    'product' => $productSlug,
                    'product_id' => (int) $product->id,
                    'variant' => $claim['variant'],
                    'variant_id' => (int) $variant->id,
                    'role' => $claim['role'],
                    'width' => $claim['width'],
                    'storage_key' => $claim['storage_key'],
                ];
            }

            if (isset($productsChecked[(int) $product->id])) {
                continue;
            }
            $productsChecked[(int) $product->id] = true;

            foreach ($product->variants()->get() as $variant) {
                $hasPrimary = ProductImage::query()
                    ->where(column: 'variant_id', operator: '=', value: $variant->id)
                    ->where(column: 'is_primary', operator: '=', value: true)
                    ->exists();

                if (! $hasPrimary) {
                    $variantsWithoutPrimary[] = [
                        'product' => $productSlug,
                        'variant' => (string) $variant->finish_slug,
                        'variant_id' => (int) $variant->id,
                    ];
                }
            }
        }

        $report['variants_without_primary'] = $variantsWithoutPrimary;
        $report['products_without_db_row'] = array_map(
            static fn (string $slug, int $claims): array => ['product' => $slug, 'claims' => $claims],
            array_keys($productsWithoutDbRow),
            array_values($productsWithoutDbRow),
        );
    }

    /**
     * Resuelve la variante del directorio contra la BD.
     *
     * El slug del directorio es la fuente (`finish_slug`), con un fallback
     * para el caso single-variant: si el producto tiene una sola variante,
     * es esa aunque el `finish_slug` haya sido backfilled a otro valor.
     */
    private function resolveVariant(Product $product, string $variantSlug): ?ProductVariant
    {
        $bySlug = $product->variants()
            ->where(column: 'finish_slug', operator: '=', value: $variantSlug)
            ->first();

        if ($bySlug !== null) {
            return $bySlug;
        }

        $all = $product->variants()->get();

        return $all->count() === 1 ? $all->first() : null;
    }
}
