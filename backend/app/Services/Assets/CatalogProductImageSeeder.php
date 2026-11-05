<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Servicio que sube las imágenes del catálogo Panda/Kitsune al disk
 * configurado (local / ftp) y crea las filas de `product_images`.
 *
 * A diferencia de `CatalogAssetsSeeder` (el importador de La Voz), NO
 * crea productos ni variantes: los matchea por `Product.slug` y
 * `ProductVariant.finish_slug` y lo que no encuentra lo avisa y lo
 * saltea. Los SKUs de los dos catálogos no coinciden, y el
 * `updateOrCreate` por slug del importador pisaría nombres y precios de
 * este catálogo.
 *
 * Estructura esperada (`.ai/rules/assets-naming-policy.md`):
 *
 *     assets/products/{tenant}/{brand}/{product}/{variant}/{role}-{WxH}.webp
 *
 * Un finish puede tener varias variantes comprables (las tallas
 * ch/m/g/xg comparten `finish_slug`), y la PDP filtra la galería por
 * `img.variantId === selectedVariantId`: cada variante de ese finish
 * recibe su propio juego de filas, no sólo una.
 *
 * Persistencia por imagen (mismo shape que
 * `CatalogAssetsSeeder::uploadAndInsertImage`, más `is_hover`):
 *   - `is_primary` sólo en la fila del rol principal (mín.
 *     `ROLE_ORDER` presente en el directorio) con `width = 1600`.
 *   - `is_hover` en todas las resoluciones del rol `back`, la misma
 *     semántica que `ProductImageController::upload` (E1.5: la card del
 *     catálogo alterna a la foto de hover).
 *   - `width` distingue las 4 resoluciones de una misma foto.
 */
final class CatalogProductImageSeeder
{
    public function __construct(
        private readonly string $assetBase,
        private readonly string $tenantSlug,
        private readonly string $storageDisk,
        private readonly StorageKeyDeriver $deriver,
        private readonly AssetFilenameParser $filenames,
        /** Reporta sin subir archivos ni insertar filas. */
        private readonly bool $dryRun = false,
    ) {}

    /**
     * Ejecuta el seed de imágenes del tenant.
     *
     * `images_created` cuenta sólo filas NUEVAS de `product_images`
     * (una segunda corrida actualiza por `updateOrCreate` y suma 0).
     *
     * @return array{
     *   images_uploaded: int,
     *   images_skipped: int,
     *   images_created: int,
     *   errors: array<int, string>,
     *   warnings: array<int, string>,
     * }
     */
    public function seed(): array
    {
        $stats = [
            'images_uploaded' => 0,
            'images_skipped' => 0,
            'images_created' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        $discovered = $this->discoverFromFilesystem();

        if ($discovered === []) {
            $stats['warnings'][] = 'No se encontraron webps en '
                .$this->assetBase.'/products/'.$this->tenantSlug;

            return $stats;
        }

        foreach ($discovered as $productKey => $productData) {
            try {
                $this->seedOneProduct(
                    productKey: $productKey,
                    data: $productData,
                    stats: $stats,
                );
            } catch (Throwable $e) {
                $stats['errors'][] = "Product {$productKey}: ".$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Descubre assets/products/{tenant}/{brand}/{product}/{variant}/*.webp,
     * con el mismo walk que `CatalogAssetsSeeder::discoverFromFilesystem`.
     *
     * Devuelve los paths crudos por directorio de variante: el parseo de
     * role+size ocurre al sembrar, para poder avisar los archivos que no
     * parsean en vez de descartarlos en silencio.
     *
     * @return array<string, array{
     *   brand_slug: string,
     *   product_slug: string,
     *   variants: array<string, array<int, string>>,
     * }>
     */
    private function discoverFromFilesystem(): array
    {
        $tenantBase = rtrim($this->assetBase, '/').'/products/'.$this->tenantSlug;
        if (! is_dir($tenantBase)) {
            return [];
        }

        $result = [];
        $brandDirs = glob($tenantBase.'/*', GLOB_ONLYDIR) ?: [];

        foreach ($brandDirs as $brandDir) {
            $brandSlug = basename($brandDir);
            $productDirs = glob($brandDir.'/*', GLOB_ONLYDIR) ?: [];
            foreach ($productDirs as $productDir) {
                $productSlug = basename($productDir);
                $key = "{$brandSlug}/{$productSlug}";
                $result[$key] = [
                    'brand_slug' => $brandSlug,
                    'product_slug' => $productSlug,
                    'variants' => [],
                ];

                $variantDirs = glob($productDir.'/*', GLOB_ONLYDIR) ?: [];

                // Mismo fallback que el importador de La Voz: un producto
                // sin subdirectorio de variante tiene los .webp colgando
                // directo del directorio del producto.
                if ($variantDirs === [] && glob($productDir.'/*-*.webp') !== []) {
                    $variantDirs = [$productDir];
                }

                foreach ($variantDirs as $variantDir) {
                    $variantSlug = basename($variantDir);
                    $result[$key]['variants'][$variantSlug] = array_values(
                        glob($variantDir.'/*-*.webp') ?: [],
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Siembra las imágenes de un producto: matchea por slug, y por cada
     * directorio de variante replica las fotos en todas las variantes
     * comprables de ese finish.
     *
     * @param  array{
     *   brand_slug: string,
     *   product_slug: string,
     *   variants: array<string, array<int, string>>,
     * }  $data
     * @param  array{
     *   images_uploaded: int, images_skipped: int, images_created: int,
     *   errors: array<int, string>, warnings: array<int, string>,
     * }  $stats
     */
    private function seedOneProduct(
        string $productKey,
        array $data,
        array &$stats,
    ): void {
        $product = Product::query()
            ->where('slug', $data['product_slug'])
            ->first();

        if ($product === null) {
            $stats['warnings'][] = "Producto con slug='{$data['product_slug']}' "
                ."no existe; se descarta {$productKey}.";

            return;
        }

        foreach ($data['variants'] as $variantSlug => $webpPaths) {
            $entries = [];
            foreach ($webpPaths as $webpPath) {
                $parsed = $this->filenames->parseRoleAndSize(
                    base: basename($webpPath, '.webp'),
                );
                if ($parsed === null) {
                    $stats['warnings'][] = "Filename no parseable, se descarta: {$webpPath}";

                    continue;
                }
                $entries[] = [
                    'role' => $parsed['role'],
                    'size' => $parsed['size_w'],
                    'webp_path' => $webpPath,
                ];
            }

            if ($entries === []) {
                continue;
            }

            // Un finish con tallas comparte directorio de fotos: cada
            // variante comprable de ese finish reclama su propio juego
            // de filas porque la PDP filtra por variantId.
            $variants = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('finish_slug', $variantSlug)
                ->get();

            if ($variants->isEmpty()) {
                $stats['warnings'][] = "Variante con finish_slug='{$variantSlug}' "
                    ."no existe para el producto '{$product->slug}'; "
                    .'se descartan '.count($entries).' imágenes.';

                continue;
            }

            $primaryRole = $this->pickPrimaryRole(
                availableRoles: array_unique(array_column($entries, 'role')),
            );

            foreach ($variants as $variant) {
                foreach ($entries as $entry) {
                    $this->uploadAndInsertImage(
                        product: $product,
                        variant: $variant,
                        webpPath: $entry['webp_path'],
                        role: $entry['role'],
                        size: $entry['size'],
                        isPrimary: $entry['role'] === $primaryRole
                            && $entry['size'] === '1600',
                        stats: $stats,
                    );
                }
            }
        }
    }

    /**
     * Sube el webp al disk (si no está) y upsert de la fila de
     * `product_images`. Shape espejo de
     * `CatalogAssetsSeeder::uploadAndInsertImage` más `is_hover`.
     *
     * @param  string  $size  Ancho en px tal cual lo parsea el filename (`'1600'`).
     * @param  array{
     *   images_uploaded: int, images_skipped: int, images_created: int,
     *   errors: array<int, string>, warnings: array<int, string>,
     * }  $stats
     */
    private function uploadAndInsertImage(
        Product $product,
        ProductVariant $variant,
        string $webpPath,
        string $role,
        string $size,
        bool $isPrimary,
        array &$stats,
    ): void {
        $bytes = @file_get_contents($webpPath);
        if ($bytes === false) {
            $stats['errors'][] = "No pude leer {$webpPath}";

            return;
        }

        $storageKey = $this->deriver->derive(
            tenantSlug: $this->tenantSlug,
            collection: 'products',
            entity: $product->slug,
            content: $bytes,
            extension: 'webp',
        );

        $existsAlready = Storage::disk($this->storageDisk)->exists($storageKey);
        if ($existsAlready) {
            $stats['images_skipped']++;
        } elseif ($this->dryRun) {
            $stats['images_uploaded']++;
        } else {
            try {
                Storage::disk($this->storageDisk)->put($storageKey, $bytes);
                $stats['images_uploaded']++;
            } catch (Throwable $e) {
                $stats['errors'][] = "Upload falló para {$storageKey}: ".$e->getMessage();

                return;
            }
        }

        $attributes = [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'image_url' => $storageKey,
        ];
        $values = [
            'alt_text' => "{$product->name} - vista {$role}",
            'is_primary' => $isPrimary,
            // La foto back completa (todas sus resoluciones) es la que
            // la card del catálogo usa como hover (E1.5).
            'is_hover' => $role === 'back',
            'sort_order' => CatalogAssetsSeeder::ROLE_ORDER[$role] ?? 99,
            'variant_id' => $variant->id,
            'width' => (int) $size,
        ];

        if ($this->dryRun) {
            if (! ProductImage::query()->where($attributes)->exists()) {
                $stats['images_created']++;
            }

            return;
        }

        $image = ProductImage::query()->updateOrCreate(
            attributes: $attributes,
            values: $values,
        );
        if ($image->wasRecentlyCreated) {
            $stats['images_created']++;
        }
    }

    /**
     * Elige el role "primary" del directorio: el de sort_order mínimo
     * de los presentes. Si 'front' existe, ese gana; si no, el
     * siguiente role del ROLE_ORDER presente.
     *
     * @param  array<int, string>  $availableRoles
     */
    private function pickPrimaryRole(array $availableRoles): string
    {
        $bestRole = null;
        $bestOrder = PHP_INT_MAX;
        foreach ($availableRoles as $role) {
            $order = CatalogAssetsSeeder::ROLE_ORDER[$role] ?? 99;
            if ($order < $bestOrder) {
                $bestOrder = $order;
                $bestRole = $role;
            }
        }

        return $bestRole ?? 'front';
    }
}
