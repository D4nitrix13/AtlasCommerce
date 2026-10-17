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
 * Seeder — Fender American Professional Telecaster (1 product, 7 variants).
 *
 * Regla (Oct 2026): "son el mismo modelo lo que cambia es el acabado".
 * 1 product con N variants en product_variants table. Mock/carousel
 * rinde 1 card con N swatches (variant preview).
 *
 * Misma guitarra y pickup config (V-Mod III single-coil Tele); cambia
 * el body color. 7 variants Rosewood FB (3-CS, Black, Natural,
 * Oxblood, Dark Night, Aged Cherry Burst Maple, Butterscotch Blonde
 * Maple) y 2 Maple FB adicionales.
 *
 * Idempotente. Uso:
 *   php artisan db:seed --class=FenderAmericanProfessionalTelecasterSeeder
 */
final class FenderAmericanProfessionalTelecasterSeeder extends Seeder
{
    private const PRODUCT_HANDLE = 'american-professional-telecaster';

    private const PRODUCT_SERIES = 'American Professional';

    private const PRODUCT_DESCRIPTION_BASE = 'La American Professional Telecaster combina más de setenta años de innovación con pickups V-Mod III single-coil, puente top-load/string-through con ClassicGear™ Compensated Brass Bullet™ Saddles, y perfil de mástil Deep "C" con bordes rolled.';

    private const BASE_PRICE_CENTS = 169900;

    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    /**
     * @param  array{0:string,1:string,2:string,3:string,4:string}  $row
     *                                                                    [slug, displayName, sku, fingerboard, front_v]
     *                                                                    - front_v: timestamp del CDN para front image
     *                                                                    - back: compartido por todos los Rosewood (3-CS) o por todos los
     *                                                                    Maple (3-CS Maple) — el back image es el mismo entre variants
     *                                                                    de un mismo fingerboard.
     */
    private const VARIANTS = [
        // Rosewood FB
        ['3-color-sunburst', '3-Color Sunburst', 'FEND-AMPRO-TEL-0700', 'rosewood', '1788979189'],
        ['black', 'Black', 'FEND-AMPRO-TEL-0706', 'rosewood', '1788979188'],
        ['natural', 'Natural', 'FEND-AMPRO-TEL-0721', 'rosewood', '1788979189'],
        ['oxblood', 'Oxblood', 'FEND-AMPRO-TEL-0730', 'rosewood', '1788979189'],
        ['dark-night', 'Dark Night', 'FEND-AMPRO-TEL-0761', 'rosewood', '1788979188'],
        // Maple FB
        ['aged-cherry-burst', 'Aged Cherry Burst', 'FEND-AMPRO-TEL-2731', 'maple', '1788979189'],
        ['butterscotch-blonde', 'Butterscotch Blonde', 'FEND-AMPRO-TEL-2750', 'maple', '1788979188'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            // 1 product con N variants. SKU base del product (front 3-CS);
            // cada variant tiene su propia SKU para inventario.
            $productSku = 'FEND-AMPRO-TEL-0700';
            $productSlug = self::PRODUCT_HANDLE;
            $productName = 'Fender American Professional Telecaster';

            $product = $this->ensureProduct(
                sku: $productSku,
                slug: $productSlug,
                name: $productName,
                categoryId: $category->id,
                brandId: $brand->id,
                currencyId: $currency->id,
            );

            foreach (self::VARIANTS as [$slug, $displayName, $sku, $fingerboard, $frontV]) {
                $variantName = sprintf(
                    '%s — %s (%s)',
                    $productName,
                    $displayName,
                    $fingerboard === 'rosewood' ? 'Rosewood FB' : 'Maple FB'
                );

                $variant = $this->ensureVariant(
                    productId: $product->id,
                    sku: $sku,
                    name: $variantName,
                );

                $this->syncVariantImages(
                    productId: $product->id,
                    variantSlug: $slug,
                    frontV: $frontV,
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

    /**
     * Inserta 2 imágenes para esta variant (front + back). El back se
     * comparte por fingerboard (Rosewood 3-CS o Maple 3-CS según la
     * variant) — la URL incluye el variantSlug del path.
     */
    private function syncVariantImages(int $productId, string $variantSlug, string $frontV): void
    {
        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->where(column: 'alt_text', operator: 'like', value: $variantSlug.' — %')
            ->delete();

        $frontUrl = sprintf(
            '/images/products/%s/%s/%s-%s-1600x525-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::PRODUCT_HANDLE,
            $variantSlug,
            self::FRONT_INDEX,
        );
        // Back compartido por fingerboard: 0111140700 (3-CS Rosewood) para
        // variants Rosewood, 0111142700 (3-CS Maple) para variants Maple.
        $backSku = str_contains($variantSlug, 'maple') ? '0111142700' : '0111140700';
        $backUrl = sprintf(
            '/images/products/%s/%s/%s-%s-1600x525-%s.webp',
            self::PRODUCT_HANDLE,
            $variantSlug,
            $backSku,
            $backSku,
            self::BACK_INDEX,
        );

        $images = [
            ['url' => $frontUrl, 'is_primary' => true, 'sort_order' => 0],
            ['url' => $backUrl, 'is_primary' => false, 'sort_order' => 1],
        ];

        foreach ($images as $img) {
            ProductImage::query()->create(attributes: [
                'product_id' => $productId,
                'image_url' => $img['url'],
                'alt_text' => sprintf('%s — vista %s', $variantSlug, str_contains($img['url'], '1600x525-01') ? '01' : '02'),
                'is_primary' => $img['is_primary'],
                'sort_order' => $img['sort_order'],
            ]);
        }
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

            // Por cada variant, recrear las 2 imágenes (front + back) con
            // la URL relativa al CDN/frontend público del tenant.
            foreach (self::VARIANTS as [$slug, $displayName, $sku, $fingerboard, $frontV]) {
                $frontUrl = sprintf(
                    '/images/products/%s/%s/%s-%s-1600x525-%s.webp',
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::PRODUCT_HANDLE,
                    $slug,
                    self::FRONT_INDEX,
                );
                $backSku = str_contains($slug, 'maple') ? '0111142700' : '0111140700';
                $backUrl = sprintf(
                    '/images/products/%s/%s/%s-%s-1600x525-%s.webp',
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
