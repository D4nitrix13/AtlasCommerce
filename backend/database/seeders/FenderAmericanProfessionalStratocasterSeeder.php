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
 * Seeder — Fender American Professional Stratocaster (7 variants).
 *
 * Variantes del modelo AmPro Strat (modelo hermano del AmPro Tele
 * que cubre `FenderAmericanProfessionalTelecasterSeeder`). Mismas
 * specs, distinta pastilla (3 single-coil V-Mod III vs 2 single-coil
 * del Tele) y distinto body shape (Stratocaster vs Telecaster).
 *
 * Imágenes del recon round 1 (Oct 2026). URLs del CDN de Fender:
 *   - Front: 0111110{XXX}_fen_ins_frt_1_rr.png?v=17889791{YY}
 *   - Back:  0111110700_fen_ins_bck_1_rl.png?v=1788979193 (SHARED
 *     entre TODAS las 12 variants Rosewood y Maple)
 *
 * Estructura de archivos en disco (sigue convención del proyecto):
 *   frontend/public/images/products/fender-american-professional-stratocaster/{variant}/
 *     fender-american-professional-stratocaster-{variant}-1600x525-01.webp  (front)
 *     fender-american-professional-stratocaster-{variant}-1600x521-02.webp  (back, 3-CS SKU)
 *
 * Idempotente. Uso:
 *   php artisan db:seed --class=FenderAmericanProfessionalStratocasterSeeder
 */
final class FenderAmericanProfessionalStratocasterSeeder extends Seeder
{
    private const PRODUCT_HANDLE = 'american-professional-stratocaster';

    private const PRODUCT_SERIES = 'American Professional';

    private const PRODUCT_DESCRIPTION_BASE = 'La American Professional Stratocaster combina más de setenta años de innovación con pickups V-Mod III single-coil Strat, trémolo 2-point con ClassicGear™ Radial™ bent-steel saddles, y perfil de mástil Deep "C" con bordes rolled.';

    private const BASE_PRICE_CENTS = 169900;

    private const SIZE_1600 = '1600x525';

    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    /**
     * Variantes con SKUs reales de Shopify (7 últimos dígitos = 01111107XX).
     * `front_v` = timestamp del CDN para front image.
     * Back: SHARED — todas las variants usan `0111110700_fen_ins_bck_1_rl.png?v=1788979193`.
     *
     * @param  array{0:string,1:string,2:string,3:string,4:string}  $row
     *                                                                    [slug, displayName, sku, fingerboard, front_v]
     */
    private const VARIANTS = [
        // Rosewood FB
        ['3-color-sunburst', '3-Color Sunburst', 'FEND-AMPRO-STR-0700', 'rosewood', '1788979191'],
        ['olympic-white', 'Olympic White', 'FEND-AMPRO-STR-0705', 'rosewood', '1788979192'],
        ['black', 'Black', 'FEND-AMPRO-STR-0706', 'rosewood', '1788979191'],
        ['oxblood', 'Oxblood', 'FEND-AMPRO-STR-0730', 'rosewood', '1788979191'],
        ['dark-night', 'Dark Night', 'FEND-AMPRO-STR-0761', 'rosewood', '1788979191'],
        // Maple FB
        ['3-color-sunburst-maple', '3-Color Sunburst', 'FEND-AMPRO-STR-2700', 'maple', '1788979190'],
        ['olympic-white-maple', 'Olympic White', 'FEND-AMPRO-STR-2705', 'maple', '1788979191'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            foreach (self::VARIANTS as [$slug, $displayName, $sku, $fingerboard, $frontV]) {
                $productSlug = sprintf('%s-%s', self::PRODUCT_HANDLE, $slug);
                $productName = sprintf('Fender %s, %s Stratocaster, %s', self::PRODUCT_SERIES, $displayName, $fingerboard === 'rosewood' ? 'Rosewood Fingerboard' : 'Maple Fingerboard');
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
