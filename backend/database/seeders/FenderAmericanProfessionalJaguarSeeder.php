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
 * Seeder — Fender American Professional Jaguar (5 variants).
 *
 * Mismo approach que FenderAmericanProfessionalTelecasterSeeder:
 * 1 product con N variants (color = variant), 1 card por variant en
 * la galería RELATED del PDP.
 *
 * Model: Jaguar (offset 24" scale, 1 humbucker + 1 single-coil,
 * Panorama tremolo). SKU prefix 01118207XX (Rosewood FB).
 *
 * Imágenes del recon round 1 (Oct 2026) — Jaguar NO Jazzmaster.
 * URL pattern: 01118207{XX}_fen_ins_frt_1_rr.png?v=1788979{15X}
 *
 * Idempotente. Uso:
 *   php artisan db:seed --class=FenderAmericanProfessionalJaguarSeeder
 */
final class FenderAmericanProfessionalJaguarSeeder extends Seeder
{
    private const PRODUCT_HANDLE = 'american-professional-jaguar';

    private const PRODUCT_SERIES = 'American Professional';

    private const PRODUCT_DESCRIPTION_BASE = 'La American Professional Jaguar combina el scale 24" único de la Jaguar con pickups V-Mod III Jaguar single-coil, trémolo Panorama, y el perfil de mástil Deep "C" con bordes rolled.';

    private const BASE_PRICE_CENTS = 189900;

    private const SIZE_1600 = '1600x545';

    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    /**
     * @param  array{0:string,1:string,2:string}  $row  [slug, displayName, sku]
     */
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

            foreach (self::VARIANTS as [$slug, $displayName, $sku]) {
                $productSlug = sprintf('%s-%s', self::PRODUCT_HANDLE, $slug);
                $productName = sprintf('Fender %s, %s Jaguar', self::PRODUCT_SERIES, $displayName);
                $variantName = sprintf('%s — %s', $productName, $displayName);

                $product = $this->ensureProduct(
                    sku: $sku,
                    slug: $productSlug,
                    name: $productName,
                    categoryId: $category->id,
                    brandId: $brand->id,
                    currencyId: $currency->id,
                );

                $this->ensureVariant(
                    productId: $product->id,
                    sku: $sku,
                    name: $variantName,
                );

                $this->syncProductImages(
                    productId: $product->id,
                    variantSlug: $slug,
                );

                $this->syncStoreFrontImages(
                    productId: $product->id,
                    variantSlug: $slug,
                );
            }
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

    private function syncProductImages(int $productId, string $variantSlug): void
    {
        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->delete();

        $images = [
            ['index' => self::FRONT_INDEX, 'is_primary' => true],
            ['index' => self::BACK_INDEX, 'is_primary' => false],
        ];

        foreach ($images as $idx => $img) {
            $imageUrl = $this->imageUrl(
                variantSlug: $variantSlug,
                size: self::SIZE_1600,
                index: $img['index'],
            );
            ProductImage::query()->create(attributes: [
                'product_id' => $productId,
                'image_url' => $imageUrl,
                'alt_text' => sprintf('%s — vista %s', $variantSlug, $img['index']),
                'is_primary' => $img['is_primary'],
                'sort_order' => $idx,
            ]);
        }
    }

    private function syncStoreFrontImages(int $productId, string $variantSlug): void
    {
        $productStores = ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->get();

        foreach ($productStores as $productStore) {
            ProductStoreImage::query()
                ->where(column: 'product_store_id', operator: '=', value: $productStore->id)
                ->delete();

            $images = [
                ['index' => self::FRONT_INDEX, 'is_primary' => true],
                ['index' => self::BACK_INDEX, 'is_primary' => false],
            ];

            foreach ($images as $idx => $img) {
                $imageUrl = $this->imageUrl(
                    variantSlug: $variantSlug,
                    size: self::SIZE_1600,
                    index: $img['index'],
                );
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $imageUrl,
                    'alt_text' => sprintf('%s — vista %s', $variantSlug, $img['index']),
                    'is_primary' => $img['is_primary'],
                    'sort_order' => $idx,
                ]);
            }
        }
    }

    private function imageUrl(string $variantSlug, string $size, string $index): string
    {
        return sprintf(
            '/images/products/%s/%s/%s-%s-%s-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::PRODUCT_HANDLE,
            $variantSlug,
            $size,
            $index,
        );
    }
}
