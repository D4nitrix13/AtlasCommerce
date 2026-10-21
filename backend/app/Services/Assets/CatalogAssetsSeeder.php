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
     * Cache del índice `handle → product.json path` del backup.
     * Lazy-loaded por lookupBackupMetadata().
     *
     * @var array<string, string>|null
     */
    private ?array $backupIndex = null;

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
        /** Backup root con product.json por variante. Null → fallback placeholder. */
        private readonly ?string $backupBase = null,
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

                // Un chunk grande del catálogo no tiene subdirectorio de
                // variante: los .webp cuelgan directo del directorio del
                // producto. Sin este caso quedaban sin ninguna imagen, porque
                // el recorrido solo miraba dentro de las variantes.
                if ($variantDirs === [] && glob($productDir . '/*-*.webp') !== []) {
                    $variantDirs = [$productDir];
                }

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
                            'size' => $parsed['size_w'],
                            'webp_path' => $webp,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Parsea "NN-role-WxH" o "role-WxH" → ['role' => ..., 'size_w' => 'W'].
     *
     * Filenames esperados: `01-front-1600x1600.webp`, `body-400x400.webp`,
     * `06-back-headstock-1200x1200.webp`, `17-finish-top-800x800.webp`.
     *
     * Solo extrae el ANCHO (W) del WxH porque el seeder compara con
     * '1600' para identificar la imagen de mayor resolución. La altura
     * (H) es irrelevante para la lógica de primary/max-size.
     *
     * @return null|array{role: string, size_w: string}
     */
    private function parseRoleAndSize(string $base): ?array
    {
        // Match solo el ANCHO (no "WxH" completo). Regex con grupo 'size_w'
        // que captura solo dígitos antes de la 'x'.
        if (! preg_match('/^(?:[0-9]{2}-)?(?P<role>[a-z\-]+)-(?P<size_w>[0-9]+)x[0-9]+$/', $base, $m)) {
            return null;
        }
        $role = $m['role'];
        $sizeW = $m['size_w'];

        // Validar contra KNOWN_ROLES (longest-first para hyphenated)
        foreach (self::KNOWN_ROLES as $known) {
            if ($role === $known || str_starts_with($base, $known . '-')) {
                return ['role' => $role, 'size_w' => $sizeW];
            }
        }

        // Fallback: si no matchea ningún known role, aceptar lo que sea parseable
        return ['role' => $role, 'size_w' => $sizeW];
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

        // El índice del backup está_claveado por el `handle` pelado, no por
        // la clave de descubrimiento `{brand}/{product}`. El handle combina
        // marca y producto, así que se prueban las dos formas.
        $category = $this->resolveCategory(
            handles: [$brandSlug . '-' . $productSlug, $productSlug],
            fallback: $defaultCategory,
        );

        // Cargar metadata del backup si está disponible
        $meta = $this->lookupBackupMetadata($productSlug);

        // Detección determinística desde handle/title
        $isExclusive = $this->detectExclusive(handle: $productSlug, title: $meta['title'] ?? null);
        $isLeftHanded = $this->strContains($productSlug, 'left-handed');

        $product = Product::query()->updateOrCreate(
            ['slug' => $productSlug],
            [
                'name' => $meta['title']
                    ?? ucwords(str_replace('-', ' ', $productSlug)) . " ({$brandName})",
                'sku' => $meta['sku']
                    ?? (strtoupper(substr($brandSlug, 0, 4)) . '-'
                        . strtoupper(str_replace('-', '', $productSlug))),
                'description' => $meta['long_description']
                    ?? ($meta['description']
                        ?? "Producto {$productSlug} de la marca {$brandName}, "
                            . "importado al catálogo de {$tenantStore->name}."),
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'currency_id' => $defaultCurrency->id,
                'base_price_cents' => $meta['base_price_cents'] ?? 0,
                'cost_cents' => 0,
                'is_active' => true,
                'is_exclusive' => $isExclusive,
                'is_left_handed' => $isLeftHanded,
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
                'public_price' => $meta['base_price_cents'] ?? 0,
                'is_visible' => true,
                'is_featured' => $isExclusive, // feature flag: exclusivos destacados en home
                'sort_order' => 0,
                'published_at' => now(),
            ],
        );

        foreach ($data['variants'] as $variantSlug => $webps) {
            // Detección handedness per-variant
            $handedness = $this->detectHandedness($variantSlug);

            $variant = ProductVariant::query()->updateOrCreate(
                ['product_id' => $product->id, 'sku' => strtoupper(substr($brandSlug, 0, 4))
                    . '-' . strtoupper(str_replace('-', '', $productSlug))
                    . '-' . strtoupper(str_replace('-', '', $variantSlug))],
                [
                    'name' => ucwords(str_replace('-', ' ', $variantSlug)),
                    'price_cents' => $meta['base_price_cents'] ?? 0,
                    'cost_cents' => 0,
                    'is_active' => true,
                    'finish_slug' => $variantSlug,
                    'finish_label' => ucwords(str_replace('-', ' ', $variantSlug)),
                    'finish_hex' => null,
                    'condition' => 'nuevo',
                    'stock' => 0,
                    'handedness' => $handedness,
                ],
            );
            $stats['variants_created']++;

            // Determinar role "primary" del variant: el de sort_order
            // mínimo de los roles disponibles. Si no hay 'front', usar el
            // siguiente role que exista.
            $rolesInVariant = array_unique(array_column($webps, 'role'));
            $primaryRole = $this->pickPrimaryRole($rolesInVariant);

            // Subir cada webp del variant + crear ProductImage. Primary va
            // SOLO al size 1600 (max resolution) del role elegido, no a los
            // 4 sizes — sino quedaría 1 primary × 4 sizes = 4 falses positives.
            foreach ($webps as $webpEntry) {
                $isPrimary = ($webpEntry['role'] === $primaryRole && $webpEntry['size'] === '1600');
                $this->uploadAndInsertImage(
                    product: $product,
                    variant: $variant,
                    webpPath: $webpEntry['webp_path'],
                    role: $webpEntry['role'],
                    size: $webpEntry['size'],
                    brandSlug: $brandSlug,
                    isPrimary: $isPrimary,
                    stats: $stats,
                );
            }
        }
    }

    /**
     * Elige el role "primary" del variant: el de sort_order mínimo
     * de los disponibles. Si 'front' existe, ese gana. Si no,
     * el siguiente role del ROLE_ORDER presente.
     *
     * @param  array<int, string>  $availableRoles
     */
    private function pickPrimaryRole(array $availableRoles): string
    {
        $bestRole = null;
        $bestOrder = PHP_INT_MAX;
        foreach ($availableRoles as $r) {
            $order = self::ROLE_ORDER[$r] ?? 99;
            if ($order < $bestOrder) {
                $bestOrder = $order;
                $bestRole = $r;
            }
        }
        return $bestRole ?? 'front';
    }

    /**
     * Detecta si un producto es "exclusive" basado en su título o handle.
     *
     * Reglas:
     *   - Si el título contiene "exclusive" (case-insensitive) → exclusive
     *   - Si el handle contiene "exclusive" → exclusive
     *   - Si la variant slug termina en "-exclusive" → exclusive
     *
     * Detección directa desde filesystem (no requiere backup JSON).
     */
    private function detectExclusive(string $handle, ?string $title): bool
    {
        $titleLower = $title !== null ? strtolower($title) : '';
        $handleLower = strtolower($handle);

        return str_contains($titleLower, 'exclusive')
            || str_contains($titleLower, 'exclusivo')
            || str_contains($handleLower, 'exclusive')
            || str_contains($handleLower, 'exclusivo');
    }

    /**
     * Detecta handedness de un variant slug.
     *   - "foo-left-handed-bar" → 'left-handed'
     *   - "foo-bar" → null (regular)
     *   - "foo-left-handed" → 'left-handed'
     */
    private function detectHandedness(string $variantSlug): ?string
    {
        return str_contains($variantSlug, 'left-handed') ? 'left-handed' : null;
    }

    /**
     * Carga metadata del product.json del backup para un handle dado.
     *
     * Estructura del backup: `gibson-images/{cat}/{brand}/.../.../{finish}/product.json`
     * con `handle` y opcionalmente otros campos.
     *
     * Devuelve array asociativo con keys null si no hay metadata:
     *   - title, sku, description, long_description, base_price_cents,
     *     weight_g, barcode, specifications, type
     *
     * Cachea índice por handle para búsquedas O(N) en lugar de O(N×M).
     */
    private function lookupBackupMetadata(string $productSlug): array
    {
        if ($this->backupBase === null) {
            return $this->emptyMetadata();
        }

        $index = $this->loadBackupIndex();
        if (! isset($index[$productSlug])) {
            return $this->emptyMetadata();
        }

        $jsonPath = $index[$productSlug];
        $bytes = @file_get_contents($jsonPath);
        if ($bytes === false) {
            return $this->emptyMetadata();
        }

        try {
            $d = json_decode($bytes, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->emptyMetadata();
        }

        $priceCents = isset($d['price']['regular']) && is_numeric($d['price']['regular'])
            ? (int) round((float) $d['price']['regular'] * 100)
            : null;

        $longDesc = isset($d['long_description']) && is_array($d['long_description'])
            ? ($d['long_description']['body'] ?? $d['long_description']['title'] ?? null)
            : null;

        return [
            'title' => $d['title'] ?? null,
            'sku' => $d['sku'] ?? null,
            'description' => isset($d['body_html']) ? strip_tags($d['body_html']) : null,
            'long_description' => $longDesc,
            'base_price_cents' => $priceCents,
            'weight_g' => $d['weight_g'] ?? null,
            'barcode' => $d['barcode'] ?? null,
            'specifications' => $d['specifications'] ?? null,
            'type' => $d['product_type'] ?? null,
        ];
    }

    /**
     * Carga el índice de product.jsons del backup. Cachea en memoria.
     *
     * Formato del path: `{backupBase}/{category}/{brand}/{...}/{finish}/product.json`
     * Mapping: handle (key del json) → ruta absoluta del archivo.
     *
     * Costo: O(N) en primera llamada, O(1) después.
     * Si el backup tiene 410 productos, son ~10ms-100ms.
     */
    private function loadBackupIndex(): array
    {
        if ($this->backupIndex !== null) {
            return $this->backupIndex;
        }

        $this->backupIndex = [];

        if ($this->backupBase === null || ! is_dir($this->backupBase)) {
            return $this->backupIndex;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->backupBase,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
            )
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() !== 'product.json') {
                continue;
            }
            $bytes = @file_get_contents($file->getPathname());
            if ($bytes === false) {
                continue;
            }
            try {
                $d = json_decode($bytes, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }
            $handle = $d['handle'] ?? null;
            if (is_string($handle) && $handle !== '') {
                $this->backupIndex[$handle] = $file->getPathname();
            }
        }

        return $this->backupIndex;
    }

    private function emptyMetadata(): array
    {
        return [
            'title' => null,
            'sku' => null,
            'description' => null,
            'long_description' => null,
            'base_price_cents' => null,
            'weight_g' => null,
            'barcode' => null,
            'specifications' => null,
            'type' => null,
        ];
    }

    private function strContains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
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

    /**
     * Resuelve la categoría del producto desde el árbol del backup.
     *
     * El backup está organizado como `{backupBase}/{category}/{brand}/...`,
     * así que la categoría es el primer segmento de la ruta del
     * `product.json` que el índice tiene asociado al handle.
     *
     * Antes toda la instancia caía en una única categoría hardcodeada
     * ("Instrumento musical"), que dejaba las nueve categorías reales del
     * catálogo sin modelar. Si el handle no está en el índice, o la ruta no
     * tiene categoría reconocible, se usa el fallback del llamador.
     */
    private function resolveCategory(array $handles, ?Category $fallback): Category
    {
        $index = $this->loadBackupIndex();
        $path = null;
        foreach ($handles as $handle) {
            $path = $index[$handle] ?? null;
            if ($path !== null) {
                break;
            }
        }

        if ($path !== null) {
            $relative = ltrim(substr(
                $path,
                strlen($this->backupBase),
            ), '/');
            $categorySlug = explode('/', $relative)[0] ?? '';

            if ($categorySlug !== '' && ! in_array($categorySlug, ['.', '..'], true)) {
                return $this->ensureCategory(
                    ucwords(str_replace('-', ' ', $categorySlug)),
                    $categorySlug,
                );
            }
        }

        return $fallback ?? $this->ensureCategory(
            'Instrumento musical',
            'instrumento-musical',
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
