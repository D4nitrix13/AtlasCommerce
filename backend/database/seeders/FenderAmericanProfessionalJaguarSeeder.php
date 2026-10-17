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

/**
 * Seeder — Fender American Professional Jaguar (1 product, 5 variants).
 *
 * Mismo pattern que Tele y Strat: 1 product con N variants. Distinto
 * del Jazzmaster (user confirmó Jaguar, SKUs 01118207XX).
 *
 * Scale 24" corto, 1 humbucker + 1 single-coil, trémolo Panorama.
 * 5 variants Rosewood FB.
 */
final class FenderAmericanProfessionalJaguarSeeder extends Seeder
{
    private const PRODUCT_HANDLE = 'american-professional-jaguar';

    private const PRODUCT_SERIES = 'American Professional';

    private const PRODUCT_DESCRIPTION_BASE = 'La American Professional Jaguar combina el scale 24" único de la Jaguar con pickups V-Mod III Jaguar single-coil, trémolo Panorama, y perfil de mástil Deep "C" con bordes rolled.';

    private const BASE_PRICE_CENTS = 189900;

    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    /** @param array{0:string,1:string,2:string} $row [slug, displayName, sku] */
    private const VARIANTS = [
        ['3-color-sunburst', '3-Color Sunburst', 'FEND-AMPRO-JAG-0700'],
        ['olympic-white', 'Olympic White', 'FEND-AMPRO-JAG-0705'],
        ['black', 'Black', 'FEND-AMPRO-JAG-0706'],
        ['oxblood', 'Oxblood', 'FEND-AMPRO-JAG-0730'],
        ['dark-night', 'Dark Night', 'FEND-AMPRO-JAG-0761'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            $productSku = 'FEND-AMPRO-JAG-0700';
            $productSlug = self::PRODUCT_HANDLE;
            $productName = 'Fender American Professional Jaguar';

            $product = $this->ensureProduct(
                sku: $productSku,
                slug: $productSlug,
                name: $productName,
                categoryId: $category->id,
                brandId: $brand->id,
                currencyId: $currencyId,
            );

            foreach (self::VARIANTS as [$slug, $displayName, $sku]) {
                $variantName = sprintf('%s — %s', $productName, $displayName);

                $this->ensureVariant(
                    productId: $product->id,
                    sku: $sku,
                    name: $variantName,
                );

                $this->syncVariantImages(
                    productId: $product->id,
                    variantSlug: $slug,
                );
            }

            $this->syncStoreFrontImages(
                productId: $product->id,
            );
        });
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

    private function ensureProduct(
        string $sku,
        string $slug,
        string $name,
        int $categoryId,
        int $brandId,
        int $currencyId,
    ): Product {
        return Product::query()->updateOrCreate(
            attributes: ['sku' => $sku],
            values: [
                'name' => $name,
                'slug' => $slug,
                'description' => self::PRODUCT_DESCRIPTION_BASE,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'currency_id' => $currencyId,
                'base_price_cents' => self::BASE_PRICE_CENTS,
                'cost_cents' => (int) (self::BASE_PRICE_CENTS * 0.5),
                'is_active' => true,
                'has_condition' => false,
                'has_sizes' => false,
                'published_at' => now(),
            ],
        );
    }

    private function ensureVariant(int $productId, string $sku, string $name): ProductVariant
    {
        return ProductVariant::query()->updateOrCreate(
            attributes: [
                'sku' => $sku,
                'product_id' => $productId,
            ],
            values: [
                'name' => $name,
                'price_cents' => self::BASE_PRICE_CENTS,
                'cost_cents' => (int) (self::BASE_PRICE_CENTS * 0.5),
                'is_active' => true,
            ],
        );
    }

    private function syncVariantImages(int $productId, string $variantSlug): void
    {
        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->where(column: 'alt_text', operator: 'like', value: $variantSlug.' — %')
            ->delete();

        $frontUrl = sprintf(
            '/images/products/%s/%s/%s-%s-1600x545-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::FRONT_INDEX,
        );
        // Back image compartido: 0111820700 (3-CS Rosewood Jaguar).
        $backUrl = sprintf(
            '/images/products/%s/%s/0111820700-0111820700-1600x543-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::BACK_INDEX,
        );

        ProductImage::query()->create(attributes: [
            'product_id' => $productId,
            'image_url' => $frontUrl,
            'alt_text' => sprintf('%s — vista %s', $variantSlug, self::FRONT_INDEX),
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        ProductImage::query()->create(attributes: [
            'product_id' => $productId,
            'image_url' => $backUrl,
            'alt_text' => sprintf('%s — vista %s', $variantSlug, self::BACK_INDEX),
            'is_primary' => false,
            'sort_order' => 1,
        ]);
    }

    private function syncStoreFrontImages(int $productId): void
    {
        $productStores = ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->get();

        foreach ($productStores as $productStore) {
            ProductStoreImage::query()
                ->where(column: 'product_store_id', operator: '=', value: $productStore->id)
                ->delete();

            foreach (self::VARIANTS as [$slug, $displayName, $sku]) {
                $frontUrl = sprintf(
                    '/images/products/%s/%s/%s-%s-1600x545-%s.webp',
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::FRONT_INDEX,
                );
                $backUrl = sprintf(
                    '/images/products/%s/%s/0111820700-0111820700-1600x543-%s.webp',
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::BACK_INDEX,
                );
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $frontUrl,
                    'alt_text' => sprintf('%s — vista %s', $slug, self::FRONT_INDEX),
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $backUrl,
                    'alt_text' => sprintf('%s — vista %s', $slug, self::BACK_INDEX),
                    'is_primary' => false,
                    'sort_order' => 1,
                ]);
            }
        }
    }
}
