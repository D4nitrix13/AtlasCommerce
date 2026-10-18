<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\ProductStoreImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Seeder — Catálogo demo genérico, JSON-driven.
 *
 * Reemplaza los seeders concretos (`FenderAmericanProfessionalTelecaster`,
 * `GibsonModCollection`, etc.) con uno solo que itera sobre
 * `database/seeds/products.json`. Brand-agnostic: cada producto
 * describe su propio `image_pattern` para soportar las dos
 * convenciones que conviven en el catálogo:
 *
 *   - "indexed" (Fender AmPro, Les Paul Supreme):
 *       filename = `{slug}-{variant}-{WxH}-{idx}.webp`
 *   - "keyword" (Gibson Mod Collection):
 *       filename = `{slug}-{variant}-{variant}-{front|back}-{WxH}.webp`
 *
 * Idempotente: usa `updateOrCreate` y borra imágenes previas por
 * prefijo de `alt_text` antes de re-crear. Una transacción por
 * producto para mantener consistencia.
 *
 * Uso:
 *   php artisan db:seed --class=ProductSeeder
 */
final class ProductSeeder extends Seeder
{
    /** Path del JSON declarativo (relativo a `database/`). */
    private const JSON_PATH = 'seeds/products.json';

    public function run(): void
    {
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

        $this->syncStoreFrontImages(
            productId: $product->id,
            productHandle: $productData['handle'],
            imagePattern: $productData['image_pattern'],
            variants: $productData['variants'] ?? [],
        );
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

    /**
     * Sincroniza las imágenes del storefront (por cada tienda donde el
     * producto está publicado). Idempotente: borra y recrea.
     *
     * @param  array<string, mixed>  $imagePattern
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncStoreFrontImages(
        int $productId,
        string $productHandle,
        array $imagePattern,
        array $variants,
    ): void {
        $productStores = ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->get();

        foreach ($productStores as $productStore) {
            ProductStoreImage::query()
                ->where(column: 'product_store_id', operator: '=', value: $productStore->id)
                ->delete();

            foreach ($variants as $variant) {
                $variantSlug = $variant['slug'];
                $backSku = $variant['back_sku'] ?? null;

                $frontUrl = $this->buildImageUrl(
                    productHandle: $productHandle,
                    variantSlug: $variantSlug,
                    imagePattern: $imagePattern,
                    role: 'front',
                    backSku: $backSku,
                );
                $backUrl = $this->buildImageUrl(
                    productHandle: $productHandle,
                    variantSlug: $variantSlug,
                    imagePattern: $imagePattern,
                    role: 'back',
                    backSku: $backSku,
                );

                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $frontUrl,
                    'alt_text' => sprintf('%s — vista %s', $variantSlug, $this->indexFor(imagePattern: $imagePattern, role: 'front')),
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $backUrl,
                    'alt_text' => sprintf('%s — vista %s', $variantSlug, $this->indexFor(imagePattern: $imagePattern, role: 'back')),
                    'is_primary' => false,
                    'sort_order' => 1,
                ]);
            }
        }
    }
}
