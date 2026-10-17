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
 * Seeder — Fender American Professional Stratocaster (1 product, 7 variants).
 *
 * Regla (Oct 2026): "son el mismo modelo lo que cambia es el acabado".
 * 1 product con N variants en product_variants table.
 *
 * Mismo seeder pattern que FenderAmericanProfessionalTelecasterSeeder:
 * - 1 product row
 * - 1 variant row por color
 * - 2 product_images por product (front 3-CS + back 3-CS compartido)
 * - 2 product_store_images por variant para cada store
 */
final class FenderAmericanProfessionalStratocasterSeeder extends Seeder
{
    private const PRODUCT_HANDLE = 'american-professional-stratocaster';

    private const PRODUCT_SERIES = 'American Professional';

    private const PRODUCT_DESCRIPTION_BASE = 'La American Professional Stratocaster combina más de setenta años de innovación con pickups V-Mod III single-coil Strat, trémolo 2-point con ClassicGear™ Radial™ bent-steel saddles, y perfil de mástil Deep "C" con bordes rolled.';

    private const BASE_PRICE_CENTS = 169900;

    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    /** @param array{0:string,1:string,2:string,3:string} $row [slug, displayName, sku, fingerboard] */
    private const VARIANTS = [
        // Rosewood FB
        ['3-color-sunburst', '3-Color Sunburst', 'FEND-AMPRO-STR-0700', 'rosewood'],
        ['olympic-white', 'Olympic White', 'FEND-AMPRO-STR-0705', 'rosewood'],
        ['black', 'Black', 'FEND-AMPRO-STR-0706', 'rosewood'],
        ['oxblood', 'Oxblood', 'FEND-AMPRO-STR-0730', 'rosewood'],
        ['dark-night', 'Dark Night', 'FEND-AMPRO-STR-0761', 'rosewood'],
        // Maple FB
        ['3-color-sunburst-maple', '3-Color Sunburst (Maple)', 'FEND-AMPRO-STR-2700', 'maple'],
        ['olympic-white-maple', 'Olympic White (Maple)', 'FEND-AMPRO-STR-2705', 'maple'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            $productSku = 'FEND-AMPRO-STR-0700';
            $productSlug = self::PRODUCT_HANDLE;
            $productName = 'Fender American Professional Stratocaster';

            $product = $this->ensureProduct(
                sku: $productSku,
                slug: $productSlug,
                name: $productName,
                categoryId: $category->id,
                brandId: $brand->id,
                currencyId: $currencyId,
            );

            foreach (self::VARIANTS as [$slug, $displayName, $sku, $fingerboard]) {
                $variantName = sprintf(
                    '%s — %s (%s)',
                    $productName,
                    $displayName,
                    $fingerboard === 'rosewood' ? 'Rosewood FB' : 'Maple FB'
                );

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
            '/images/products/%s/%s/%s-%s-1600x523-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::FRONT_INDEX,
        );
        // Back image compartido: 0111110700 (3-CS Rosewood) o 0111112700
        // (3-CS Maple) según la variant.
        $backSku = str_contains($variantSlug, 'maple') ? '0111112700' : '0111110700';
        $backUrl = sprintf(
            '/images/products/%s/%s/%s-%s-1600x521-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            $backSku,
            $backSku,
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

            foreach (self::VARIANTS as [$slug, $displayName, $sku, $fingerboard]) {
                $frontUrl = sprintf(
                    '/images/products/%s/%s/%s-%s-1600x523-%s.webp',
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::FRONT_INDEX,
                );
                $backSku = str_contains($slug, 'maple') ? '0111112700' : '0111110700';
                $backUrl = sprintf(
                    '/images/products/%s/%s/%s-%s-1600x521-%s.webp',
                    self::PRODUCT_HANDLE,
                    $slug,
                    $backSku,
                    $backSku,
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
