<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Servicio que pobla la base de datos + storage desde
 * `assets/products/{tenant}/{brand}/{product}/`.
 *
 * ⚠️ ASUME que las imágenes en `assets/` ya están en formato WebP multi-size.
 * NO aplica algoritmo de compresión WebP — esa conversión ya se ejecutó
 * en staging vía `scripts/migrate-gibson-images.sh` antes de que los webps
 * fueran pusheados al repo.
 *
 * Responsabilidades:
 *   1. Discover: filesystem → (brand, product, variant, role, size)
 *   2. Hash:    binario → storage_key vía StorageKeyDeriver (irreversible)
 *   3. Upload:  Storage::put() al disk configurado (local / ftp)
 *   4. BD:      products, product_variants, product_stores, product_images
 *   5. Verify:  BD rows == storage_keys == hashes OK
 *
 * Orden semántico de galería (pos 1 = PRIMARY, pos 2 = HOVER):
 *   front, back, body, backbody, headstock, back-headstock, pickup, knobs,
 *   side, case, beauty, finish-top, finish-back, finish-sides, binding,
 *   fingerboard, sn-label, tailpiece, finish-peghead.
 */
final class CatalogAssetsSeeder
{
    /**
     * Orden semántico de galería. Keys = role (parseados del filename).
     */
    public const ROLE_ORDER = [
        'front' => 1,
        'back' => 2,
        'body' => 3,
        'backbody' => 4,
        'headstock' => 5,
        'back-headstock' => 6,
        'pickup' => 7,
        'knobs' => 8,
        'side' => 9,
        'case' => 10,
        'beauty' => 11,
        'finish-top' => 12,
        'finish-back' => 13,
        'finish-sides' => 14,
        'binding' => 15,
        'fingerboard' => 16,
        'sn-label' => 17,
        'tailpiece' => 18,
        'finish-peghead' => 19,
    ];

    /**
     * Roles que extraemos del filename `{role}-{WxH}.webp`.
     * Keys ordenadas LONGEST-FIRST para evitar matches parciales.
     * (back-headstock antes que back, finish-top antes que finish, etc.)
     */
    private const KNOWN_ROLES = [
        'back-headstock',
        'finish-peghead',
        'finish-sides',
        'finish-top',
        'finish-back',
        'sn-label',
        'tailpiece',
        'headstock',
        'backbody',
        'fingerboard',
        'binding',
        'pickup',
        'front',
        'back',
        'body',
        'knobs',
        'side',
        'case',
        'beauty',
    ];

    public function __construct(
        private readonly string $assetBase,
        private readonly string $tenantSlug,
        private readonly string $storageDisk,
        private readonly StorageKeyDeriver $deriver,
    ) {
    }

    /**
     * Ejecuta el seed completo.
     *
     * @return array{
     *   products_created: int,
     *   variants_created: int,
     *   images_uploaded: int,
     *   images_skipped: int,
     *   errors: array<int, string>,
     *   warnings: array<int, string>,
     * }
     */
    public function seed(): array
    {
        $stats = [
            'products_created' => 0,
            'variants_created' => 0,
            'images_uploaded' => 0,
            'images_skipped' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        $discovered = $this->discoverFromFilesystem();

        if ($discovered === []) {
            $stats['warnings'][] = 'No se encontraron webps en '
                . $this->assetBase . '/products/' . $this->tenantSlug;
            return $stats;
        }

        $tenantStore = Store::query()->where('slug', $this->tenantSlug)->first();
        if ($tenantStore === null) {
            $stats['errors'][] = "Tenant store con slug='{$this->tenantSlug}' no existe. "
                . 'Ejecuta tenants:bootstrap primero.';
            return $stats;
        }
        $defaultBrand = Brand::query()->where('slug', 'gibson')->first();
        $electricGuitarCat = Category::query()
            ->where('slug', 'guitarra-electrica')
            ->first();
        if ($defaultBrand === null || $electricGuitarCat === null) {
            $stats['warnings'][] = 'Brand/Categoría seed básicos no encontrados. '
                . "Se crearán con defaults mínimos.";
        }

        $defaultCurrency = Currency::query()->where('code', 'USD')->first()
            ?? $this->ensureCurrency('USD');

        foreach ($discovered as $productKey => $productData) {
            try {
                DB::transaction(function () use (
                    $productKey,
                    $productData,
                    $tenantStore,
                    $defaultBrand,
                    $electricGuitarCat,
                    $defaultCurrency,
                    &$stats,
                ): void {
                    $this->seedOneProduct(
                        productKey: $productKey,
                        data: $productData,
                        tenantStore: $tenantStore,
                        defaultBrand: $defaultBrand,
                        defaultCategory: $electricGuitarCat,
                        defaultCurrency: $defaultCurrency,
                        stats: $stats,
                    );
                });
            } catch (\Throwable $e) {
                $stats['errors'][] = "Product {$productKey}: " . $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Descubre estructura de assets/products/{tenant}/{brand}/{product}/{variant}/{role}-{WxH}.webp
     *
     * @return array<string, array{
     *   brand_slug: string,
     *   product_slug: string,
     *   variants: array<string, array<string, array{
     *     role: string, size: string, webp_path: string
     *   }>>,
     * }>
     */
    private function discoverFromFilesystem(): array
    {
        $tenantBase = rtrim($this->assetBase, '/') . '/products/' . $this->tenantSlug;
        if (! is_dir($tenantBase)) {
            return [];
        }

        $result = [];
        $brandDirs = glob($tenantBase . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($brandDirs as $brandDir) {
            $brandSlug = basename($brandDir);
            $productDirs = glob($brandDir . '/*', GLOB_ONLYDIR) ?: [];
            foreach ($productDirs as $productDir) {
                $productSlug = basename($productDir);
                $key = "{$brandSlug}/{$productSlug}";
                $result[$key] = [
                    'brand_slug' => $brandSlug,
                    'product_slug' => $productSlug,
                    'variants' => [],
                ];

                $variantDirs = glob($productDir . '/*', GLOB_ONLYDIR) ?: [];
                foreach ($variantDirs as $variantDir) {
                    $variantSlug = basename($variantDir);
                    $result[$key]['variants'][$variantSlug] = [];
                    $webps = glob($variantDir . '/*-*.webp') ?: [];
                    foreach ($webps as $webp) {
                        $base = basename($webp, '.webp');
                        $parsed = $this->parseRoleAndSize($base);
                        if ($parsed === null) {
                            continue;
                        }
                        $result[$key]['variants'][$variantSlug][] = [
                            'role' => $parsed['role'],
                            'size' => $parsed['size'],
                            'webp_path' => $webp,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Parsea "NN-role-WxH" o "role-WxH" → ['role' => ..., 'size' => 'WxH'].
     *
     * Filenames esperados: `01-front-1600x1600.webp`, `body-400x400.webp`,
     * `06-back-headstock-1200x1200.webp`, `17-finish-top-800x800.webp`.
     *
     * @return null|array{role: string, size: string}
     */
    private function parseRoleAndSize(string $base): ?array
    {
        // Match sufijo "WxH" o "WIDTHxHEIGHT"
        if (! preg_match('/^(?:[0-9]{2}-)?(?P<role>[a-z\-]+)-(?P<size>[0-9]+x[0-9]+)$/', $base, $m)) {
            return null;
        }
        $role = $m['role'];
        $size = $m['size'];

        // Validar contra KNOWN_ROLES (longest-first para hyphenated)
        foreach (self::KNOWN_ROLES as $known) {
            if ($role === $known || str_starts_with($base, $known . '-')) {
                return ['role' => $role, 'size' => $size];
            }
        }

        // Fallback: si no matchea ningún known role, aceptar lo que sea parseable
        return ['role' => $role, 'size' => $size];
    }

    /**
     * @param  array{
     *   products_created: int, variants_created: int, images_uploaded: int,
     *   images_skipped: int, errors: array<int, string>, warnings: array<int, string>
     * }  $stats
     */
    private function seedOneProduct(
        string $productKey,
        array $data,
        Store $tenantStore,
        ?Brand $defaultBrand,
        ?Category $defaultCategory,
        Currency $defaultCurrency,
        array &$stats,
    ): void {
        $productSlug = $data['product_slug'];
        $brandSlug = $data['brand_slug'];
        $brandName = ucwords(str_replace('-', ' ', $brandSlug));

        $brand = $defaultBrand !== null && $defaultBrand->slug === $brandSlug
            ? $defaultBrand
            : $this->ensureBrand($brandName, $brandSlug);

        $category = $defaultCategory ?? $this->ensureCategory(
            'Instrumento musical',
            'instrumento-musical',
        );

        $product = Product::query()->updateOrCreate(
            ['slug' => $productSlug],
            [
                'name' => ucwords(str_replace('-', ' ', $productSlug))
                    . " ({$brandName})",
                'sku' => strtoupper(substr($brandSlug, 0, 4)) . '-'
                    . strtoupper(substr(str_replace('-', '', $productSlug), 0, 12)),
                'description' => "Producto {$productSlug} de la marca {$brandName}, "
                    . "importado al catálogo de {$tenantStore->name}.",
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'currency_id' => $defaultCurrency->id,
                'base_price_cents' => 0, // placeholder; actualizar via admin
                'cost_cents' => 0,
                'is_active' => true,
                'is_exclusive' => false,
                'has_condition' => false,
                'has_sizes' => false,
                'published_at' => now(),
            ],
        );

        $stats['products_created']++;

        ProductStore::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_id' => $tenantStore->id],
            [
                'slug' => $productSlug,
                'public_price' => 0, // placeholder
                'is_visible' => true,
                'is_featured' => false,
                'sort_order' => 0,
                'published_at' => now(),
            ],
        );

        foreach ($data['variants'] as $variantSlug => $webps) {
            $variant = ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'sku' => strtoupper(substr($brandSlug, 0, 4))
                    . '-' . strtoupper(substr(str_replace('-', '', $productSlug), 0, 12))
                    . '-' . strtoupper(substr(str_replace('-', '', $variantSlug), 0, 8))],
                [
                    'name' => ucwords(str_replace('-', ' ', $variantSlug)),
                    'price_cents' => 0,
                    'cost_cents' => 0,
                    'is_active' => true,
                    'finish_slug' => $variantSlug,
                    'finish_label' => ucwords(str_replace('-', ' ', $variantSlug)),
                    'finish_hex' => null,
                    'condition' => 'nuevo',
                    'stock' => 0,
                ],
            );
            $stats['variants_created']++;

            // Subir cada webp del variant + crear ProductImage
            foreach ($webps as $webpEntry) {
                $this->uploadAndInsertImage(
                    product: $product,
                    variant: $variant,
                    webpPath: $webpEntry['webp_path'],
                    role: $webpEntry['role'],
                    size: $webpEntry['size'],
                    brandSlug: $brandSlug,
                    stats: $stats,
                );
            }
        }
    }

    /**
     * @param  array{
     *   products_created: int, variants_created: int, images_uploaded: int,
     *   images_skipped: int, errors: array<int, string>, warnings: array<int, string>
     * }  $stats
     */
    private function uploadAndInsertImage(
        Product $product,
        ProductVariant $variant,
        string $webpPath,
        string $role,
        string $size,
        string $brandSlug,
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
        } else {
            try {
                Storage::disk($this->storageDisk)->put($storageKey, $bytes);
                $stats['images_uploaded']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = "Upload falló para {$storageKey}: " . $e->getMessage();
                return;
            }
        }

        $position = self::ROLE_ORDER[$role] ?? 99;
        $isPrimary = ($role === 'front');

        ProductImage::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'image_url' => $storageKey,
            ],
            [
                'alt_text' => "{$product->name} - vista {$role}",
                'is_primary' => $isPrimary,
                'sort_order' => $position,
            ],
        );
    }

    private function ensureBrand(string $name, string $slug): Brand
    {
        return Brand::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function ensureCategory(string $name, string $slug): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function ensureCurrency(string $code): Currency
    {
        return Currency::query()->updateOrCreate(
            ['code' => $code],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true, 'is_primary' => true],
        );
    }

    /**
     * Fase 5 del plan: verifica end-to-end.
     *
     * @return array{
     *   ok: bool,
     *   issues: array<int, string>,
     *   stats: array{
     *     products_in_db: int,
     *     variants_in_db: int,
     *     images_in_db: int,
     *     images_in_storage: int,
     *     products_with_primary: int,
     *     products_with_gallery: int,
     *   },
     * }
     */
    public function verify(): array
    {
        // Pivot product_stores evita whereHas('stores') que produce
        // "duplicate alias" en PostgreSQL con relaciones N:N explícitas.
        $storeIds = Store::query()->where('slug', $this->tenantSlug)->pluck('id');
        $productIds = DB::table('product_stores')
            ->whereIn('store_id', $storeIds)
            ->pluck('product_id');

        $tenantProducts = Product::query()->whereIn('id', $productIds)->get();

        $stats = [
            'products_in_db' => $tenantProducts->count(),
            'variants_in_db' => 0,
            'images_in_db' => 0,
            'images_in_storage' => 0,
            'products_with_primary' => 0,
            'products_with_gallery' => 0,
        ];

        $issues = [];
        foreach ($tenantProducts as $p) {
            $variants = $p->variants()->count();
            $stats['variants_in_db'] += $variants;

            $images = ProductImage::query()->where('product_id', $p->id)->get();
            $stats['images_in_db'] += $images->count();

            $primaryCount = $images->where('is_primary', true)->count();
            if ($primaryCount === 0) {
                $issues[] = "Producto {$p->slug}: sin imagen primary";
            } elseif ($primaryCount > 1) {
                $issues[] = "Producto {$p->slug}: {$primaryCount} imágenes primary (debería ser 1)";
            } else {
                $stats['products_with_primary']++;
            }

            if ($images->count() > 0) {
                $stats['products_with_gallery']++;
            }

            // Verifica que cada storage_key exista en el storage
            foreach ($images as $img) {
                if (! Storage::disk($this->storageDisk)->exists($img->image_url)) {
                    $issues[] = "Producto {$p->slug}: storage_key {$img->image_url} NO existe en disk";
                } else {
                    $stats['images_in_storage']++;
                }
            }
        }

        return [
            'ok' => $issues === [],
            'issues' => $issues,
            'stats' => $stats,
        ];
    }
}
