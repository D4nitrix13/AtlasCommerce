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
 * Seeder — Fender American Professional Telecaster (todas las variantes).
 *
 * Consolida los 7 colores oficiales del modelo en un único seeder.
 * Cada variante es su propia entrada en la tabla `products` con su
 * propio slug (postfix = nombre del color) — el handle canónico de
 * Shopify es `american-professional-telecaster` y la SKU varía por
 * color (3 últimos dígitos del patrón 01111407XX).
 *
 * Imágenes del recon round 1 (Oct 2026). Las imágenes traseras son
 * COMPARTIDAS entre todas las variantes Rosewood y entre todas las
 * variantes Maple (un único back por fingerboard, no por color).
 *
 * Estructura de archivos:
 *   frontend/public/images/products/fender-american-professional-telecaster/{variant}/
 *     fender-american-professional-telecaster-{variant}-1600x525-01.webp  (front)
 *     fender-american-professional-telecaster-{variant}-1600x521-02.webp  (back, 3-CS SKU)
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

    private const SIZE_1600 = '1600x525';

    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    /**
     * Variantes con SKUs reales de Shopify (7 últimos dígitos = 01111407XX).
     * `front_v` = timestamp del CDN para front image (`?v=`).
     * `back_v` = timestamp para back image; el back de TODAS las variants
     * Rosewood es 0111140700 a v=1788979189, y todas las Maple también
     * (mismo upload batch, misma guitarra con distinto fingerboard).
     */
    private const VARIANTS = [
        // [slug, displayName, sku, fingerboard, front_v]
        ['3-color-sunburst', '3-Color Sunburst', 'FEND-AMPRO-TEL-0700', 'rosewood', '1788979189'],
        ['black', 'Black', 'FEND-AMPRO-TEL-0706', 'rosewood', '1788979188'],
        ['natural', 'Natural', 'FEND-AMPRO-TEL-0721', 'rosewood', '1788979189'],
        ['oxblood', 'Oxblood', 'FEND-AMPRO-TEL-0730', 'rosewood', '1788979189'],
        ['dark-night', 'Dark Night', 'FEND-AMPRO-TEL-0761', 'rosewood', '1788979188'],
        ['aged-cherry-burst', 'Aged Cherry Burst', 'FEND-AMPRO-TEL-2731', 'maple', '1788979189'],
        ['butterscotch-blonde', 'Butterscotch Blonde', 'FEND-AMPRO-TEL-2750', 'maple', '1788979188'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            foreach (self::VARIANTS as [$slug, $displayName, $sku, $fingerboard, $frontV]) {
                $productSlug = sprintf('%s-%s', self::PRODUCT_HANDLE, $slug);
                $productName = sprintf('Fender %s, %s Telecaster, %s', self::PRODUCT_SERIES, $displayName, $fingerboard === 'rosewood' ? 'Rosewood Fingerboard' : 'Maple Fingerboard');
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

    private function imageUrl(
        string $variantSlug,
        string $size,
        string $index,
    ): string {
        // Path en disco: /images/products/{product-slug}/{variant-slug}/
        //                  {product-slug}-{variant-slug}-{WxH}-{NN}.webp
        // (No es la URL del CDN — el browser resuelve via static serving
        // desde /frontend/public, sin el v= de cache-busting).
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
