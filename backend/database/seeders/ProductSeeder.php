<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Services\Assets\AssetBaseResolver;
use App\Services\Catalog\VariantHandednessResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Seeder — Catálogo demo genérico, JSON-driven.
 *
 * Este seeder está marcado como @deprecated en favor de
 * `php artisan assets:seed-catalog` (que toma el árbol `assets/` y
 * deriva productos, variants y storage_keys). El seeder acá sigue
 * activo por dos razones:
 *
 *   - Retrocompat con `php artisan db:seed --class=ProductSeeder`
 *     (que sigue siendo el flujo documentado en entornos de dev donde
 *     el árbol `assets/` no llegó al checkout).
 *   - El demo "Saber Alter" de 15 productos que vive en
 *     `database/seeds/products.json` y necesita sembrarse aún cuando
 *     el árbol real del tenant no está presente.
 *
 * Decisión respecto a `AssetBaseResolver` (Oct 2026):
 *
 *   Este seeder NO requiere el árbol de assets para sembrar el
 *   catálogo demo (las URLs del demo son strings hardcoded en JSON,
 *   no storage_keys hasheados por contenido).
 *
 *   PERO el bug que motivó este refactor fue histórico: la versión
 *   que delegaba a `assets:seed-catalog` usaba `base_path('../../assets')`
 *   — eso resuelve a `/var/assets` DENTRO del container, que NO
 *   existe, y el comando callaba con "0 productos" en vez de fallar.
 *   La misma clase de fallo silencioso ya tumbó este repo dos veces
 *   (la versión defectuosa está documentada en `BackfillVariantFinishCommand`).
 *
 *   Para que ese bug no vuelva a colarse, este seeder prueba
 *   `AssetBaseResolver::resolve(null, 'instrumentos-musicales-la-voz')`
 *   como probe (no usa el asset base, sólo verifica que existe).
 *   Si no existe, falla con error explícito en vez de silenciar.
 *
 * Idempotente: usa `updateOrCreate` y borra imágenes previas por
 * prefijo de `alt_text` antes de re-crear. Una transacción por
 * producto para mantener consistencia.
 *
 * Uso:
 *   php artisan db:seed --class=ProductSeeder
 *
 * @deprecated 2026-10-19 — usar `php artisan assets:seed-catalog` para
 *                           catálogos reales. Este seeder mantiene el
 *                           demo Saber Alter.
 * @see AssetBaseResolver
 * @see \App\Console\Commands\BackfillVariantFinishCommand::resolveAssetBase()
 */
final class ProductSeeder extends Seeder
{
    /** Path del JSON declarativo (relativo a `database/`). */
    private const JSON_PATH = 'seeds/products.json';

    /** Tenant del demo. Único tenant con catálogo preexistente. */
    private const TENANT_SLUG = 'instrumentos-musicales-la-voz';

    public function __construct(
        private readonly AssetBaseResolver $assetBaseResolver = new AssetBaseResolver,
    ) {}

    public function run(): void
    {
        // Probe de la raíz de assets. No la usamos en este seeder, pero
        // la verificamos para que el patrón de fallo silencioso de
        // `base_path('../../assets')` no vuelva a colarse.
        //
        // Si la raíz no existe: error explícito y abort (en vez de
        // sembrar 0 productos y salir con exit 0 verde, que es la
        // falla silenciosa que ya tumbó el repo dos veces).
        $assetBase = $this->assetBaseResolver->resolve(
            explicit: null,
            tenantSlug: self::TENANT_SLUG,
        );

        if ($assetBase === null) {
            $this->command?->error(self::class.': no encontré el árbol de assets del tenant "'.
                self::TENANT_SLUG.'". Probé los candidatos estándar pero ninguno tiene '.
                "'products/".self::TENANT_SLUG."/'.");
            $this->command?->error('Si el catálogo vive en otra ruta, pasala por env ATLAS_ASSET_BASE.');
            $this->command?->error('Si querés saltarte el probe, sobreescribí el método en un TestCase.');

            // En testing no hay command. Lanzamos RuntimeException para
            // que PHPUnit lo reporte como falla clara en vez del silencio
            // histórico (0 productos sembrados, exit 0 verde).
            if ($this->command === null) {
                throw new RuntimeException(sprintf(
                    'ProductSeeder: no asset tree found for tenant "%s". '.'Sin el árbol, los CatalogAssetsSeeder(SeedCatalogAssetsCommand) fallan silenciosamente. '.'Verificá que `assets/products/%s/` exista en este host o setea ATLAS_ASSET_BASE.',
                    self::TENANT_SLUG,
                    self::TENANT_SLUG,
                ));
            }

            return;
        }

        $payload = $this->loadPayload();

        foreach ($payload['products'] ?? [] as $productData) {
            DB::transaction(function () use ($productData): void {
                $this->seedProduct($productData);
            });
        }
    }

    /**
     * Lee y decodifica el JSON de productos.
     *
     * @return array{products?: array<int, array<string, mixed>>}
     */
    private function loadPayload(): array
    {
        $path = database_path(self::JSON_PATH);

        if (! is_file($path)) {
            // El JSON faltante NO es normal en runtime (se commitea).
            // Si pasa en algún entorno, fallar explícito es preferible
            // a sembrar 0 productos.
            throw new RuntimeException(sprintf(
                'ProductSeeder: %s no existe. Sin el declarativo, el seeder no puede sembrar el demo.',
                $path,
            ));
        }

        /** @var array{products?: array<int, array<string, mixed>>} $decoded */
        $decoded = json_decode(
            json: file_get_contents($path),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }

    /**
     * Procesa UN producto: ensure brand/category/currency/product,
     * itera sus variants, y sincroniza imágenes del storefront.
     *
     * @param  array<string, mixed>  $productData
     */
    private function seedProduct(array $productData): void
    {
        $brand = $this->ensureBrand(
            name: $productData['brand'],
            slug: $productData['brand_slug'],
        );
        $category = $this->ensureCategory(
            name: $productData['category'],
            slug: $productData['category_slug'],
        );
        $currency = $this->ensureCurrency(code: $productData['currency']);

        $product = $this->ensureProduct(
            brandId: $brand->id,
            categoryId: $category->id,
            currencyId: $currency->id,
            data: $productData,
        );

        // El demo original no usaba `VariantHandednessResolver` (es un
        // demo de catálogo estático). Dejamos `handedness = null` por
        // compatibilidad con lo que el demo ya producía.

        $basePriceCents = (int) $productData['base_price_cents'];

        foreach ($productData['variants'] ?? [] as $variantData) {
            $this->ensureVariant(
                productId: $product->id,
                productName: $productData['name'],
                priceCents: $basePriceCents,
                data: $variantData,
            );

            $this->syncVariantImages(
                productId: $product->id,
                productHandle: $productData['handle'],
                imagePattern: $productData['image_pattern'],
                variant: $variantData,
            );
        }
    }

    private function ensureBrand(string $name, string $slug): Brand
    {
        return Brand::query()->updateOrCreate(
            attributes: ['slug' => $slug],
            values: ['name' => $name, 'is_active' => true],
        );
    }

    private function ensureCategory(string $name, string $slug): Category
    {
        return Category::query()->updateOrCreate(
            attributes: ['slug' => $slug],
            values: ['name' => $name, 'is_active' => true],
        );
    }

    private function ensureCurrency(string $code): Currency
    {
        return Currency::query()->updateOrCreate(
            attributes: ['code' => $code],
            values: ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureProduct(
        int $brandId,
        int $categoryId,
        int $currencyId,
        array $data,
    ): Product {
        $basePriceCents = (int) $data['base_price_cents'];

        return Product::query()->updateOrCreate(
            attributes: ['sku' => $data['variants'][0]['sku']],
            values: [
                'name' => $data['name'],
                'slug' => $data['handle'],
                'description' => $data['description'],
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'currency_id' => $currencyId,
                'base_price_cents' => $basePriceCents,
                'cost_cents' => (int) ($basePriceCents * 0.5),
                'is_active' => true,
                'has_condition' => $data['has_condition'] ?? false,
                'has_sizes' => $data['has_sizes'] ?? false,
                'published_at' => now(),
            ],
        );
    }

    /**
     * Crea o actualiza una variant. El nombre incluye el sufijo
     * fingerboard `(Rosewood FB)` / `(Maple FB)` cuando la variant
     * lo declara — convención del catálogo Fender AmPro.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureVariant(
        int $productId,
        string $productName,
        int $priceCents,
        array $data,
    ): ProductVariant {
        $displayName = $data['display_name'];
        $fingerboardTag = $this->fingerboardTag(fingerboard: $data['fingerboard'] ?? null);

        $variantName = $fingerboardTag === null
            ? sprintf('%s — %s', $productName, $displayName)
            : sprintf('%s — %s (%s)', $productName, $displayName, $fingerboardTag);

        return ProductVariant::query()->updateOrCreate(
            attributes: [
                'sku' => $data['sku'],
                'product_id' => $productId,
            ],
            values: [
                'name' => $variantName,
                'price_cents' => $priceCents,
                'cost_cents' => (int) ($priceCents * 0.5),
                'is_active' => true,
                'finish_slug' => $data['finish_slug'] ?? $data['slug'],
                'finish_label' => $data['finish_label'] ?? $displayName,
                'finish_hex' => $data['finish_hex'] ?? null,
                'condition' => $data['condition'] ?? 'nuevo',
                'stock' => (int) ($data['stock'] ?? 0),
                // Mantener el demo fiel al histórico: nada de resolver de
                // variante (no había cuando se escribió este demo).
                'handedness' => null,
            ],
        );
    }

    /**
     * Devuelve el tag legible de fingerboard o `null` si no aplica.
     */
    private function fingerboardTag(?string $fingerboard): ?string
    {
        return match ($fingerboard) {
            'rosewood' => 'Rosewood FB',
            'maple' => 'Maple FB',
            default => null,
        };
    }

    /**
     * Inserta 2 imágenes por variant (front + back). Borra las
     * imágenes previas con el mismo prefijo de `alt_text` antes de
     * re-crear para mantener idempotencia.
     *
     * @param  array<string, mixed>  $imagePattern
     * @param  array<string, mixed>  $variant
     */
    private function syncVariantImages(
        int $productId,
        string $productHandle,
        array $imagePattern,
        array $variant,
    ): void {
        $variantSlug = $variant['slug'];

        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->where(column: 'alt_text', operator: 'like', value: $variantSlug.' — %')
            ->delete();

        $images = [
            [
                'url' => $this->buildImageUrl(
                    productHandle: $productHandle,
                    variantSlug: $variantSlug,
                    imagePattern: $imagePattern,
                    role: 'front',
                    backSku: $variant['back_sku'] ?? null,
                ),
                'is_primary' => true,
                'sort_order' => 0,
            ],
            [
                'url' => $this->buildImageUrl(
                    productHandle: $productHandle,
                    variantSlug: $variantSlug,
                    imagePattern: $imagePattern,
                    role: 'back',
                    backSku: $variant['back_sku'] ?? null,
                ),
                'is_primary' => false,
                'sort_order' => 1,
            ],
        ];

        foreach ($images as $image) {
            ProductImage::query()->create(attributes: [
                'product_id' => $productId,
                'image_url' => $image['url'],
                'alt_text' => sprintf(
                    '%s — vista %s',
                    $variantSlug,
                    $this->indexFor(imagePattern: $imagePattern, role: $image['is_primary'] ? 'front' : 'back'),
                ),
                'is_primary' => $image['is_primary'],
                'sort_order' => $image['sort_order'],
            ]);
        }
    }

    /**
     * Construye la URL pública para una imagen según el `image_pattern`
     * del producto. Soporta dos naming conventions:
     *
     *   - "indexed": `{slug}-{variant}-{WxH}-{idx}.webp` (Fender AmPro,
     *     Les Paul Supreme). Si la variant declara `back_sku`, la imagen
     *     back usa `{backSku}-{backSku}-{WxH}-{idx}.webp` (convención
     *     histórica del catálogo Fender donde el back image se comparte
     *     por SKU de fingerboard).
     *   - "keyword": `{slug}-{variant}-{variant}-{front|back}-{WxH}.webp`
     *     (Gibson Mod Collection — el variant se duplica en el filename).
     *
     * @param  array<string, mixed>  $imagePattern
     */
    private function buildImageUrl(
        string $productHandle,
        string $variantSlug,
        array $imagePattern,
        string $role,
        ?string $backSku,
    ): string {
        $basePath = '/images/products';
        $naming = $imagePattern['naming'];
        $size = $imagePattern[$role.'_size'];
        $index = $imagePattern[$role.'_index'] ?? null;
        $keyword = $imagePattern[$role.'_keyword'] ?? null;

        if ($naming === 'indexed') {
            // Back image con back_sku declarado: filename usa el SKU
            // compartido por fingerboard en lugar del slug de variant.
            if ($role === 'back' && $backSku !== null) {
                $filename = sprintf('%s-%s-%s-%s', $backSku, $backSku, $size, $index);
            } else {
                $filename = sprintf('%s-%s-%s-%s', $productHandle, $variantSlug, $size, $index);
            }
        } elseif ($naming === 'keyword') {
            // Gibson Mod Collection: el handle ya termina con el variant
            // (e.g. `gibson-mod-collection-sg-standard-holy-shift`), así
            // que el filename queda como `{handle}-{variant}-{key}-{WxH}`
            // — el variant aparece naturalmente 2x (una por el handle,
            // una por la convención keyword) sin necesidad de duplicarlo
            // explícito.
            $filename = sprintf(
                '%s-%s-%s-%s',
                $productHandle,
                $variantSlug,
                $keyword,
                $size,
            );
        } else {
            throw new InvalidArgumentException(
                sprintf('Unsupported image_pattern.naming: %s', var_export($naming, true)),
            );
        }

        return sprintf('%s/%s/%s/%s.webp', $basePath, $productHandle, $variantSlug, $filename);
    }

    /**
     * Devuelve el índice (`NN`) o keyword (`front`/`back`) para el
     * `alt_text` de una imagen según su role.
     *
     * @param  array<string, mixed>  $imagePattern
     */
    private function indexFor(array $imagePattern, string $role): string
    {
        return match ($imagePattern['naming']) {
            'indexed' => $imagePattern[$role.'_index'],
            'keyword' => $imagePattern[$role.'_keyword'],
        };
    }
}
