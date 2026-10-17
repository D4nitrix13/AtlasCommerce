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
 * Seeder — Gibson Les Paul Supreme, Blueberry Burst (Exclusive).
 *
 * Variante exclusiva de Gibson disponible sólo en gibson.com / Gibson
 * Garage. Producto de referencia temprana del recon de imágenes
 * (Oct 2026). Imágenes square 1600×1600 (Gibson Mod Collection
 * source format).
 *
 * Estructura de archivos:
 *   frontend/public/images/products/gibson-les-paul-supreme-blueberry-burst/blueberry-burst/
 *     gibson-les-paul-supreme-blueberry-burst-blueberry-burst-{WxH}-01.webp (front, _3_Front.png)
 *     gibson-les-paul-supreme-blueberry-burst-blueberry-burst-{WxH}-02.webp (back,  _4_Back.png)
 *
 * Idempotente. Uso:
 *   php artisan db:seed --class=GibsonLesPaulSupremeBlueberryBurstSeeder
 */
final class GibsonLesPaulSupremeBlueberryBurstSeeder extends Seeder
{
    private const PRODUCT_SLUG = 'gibson-les-paul-supreme-blueberry-burst';

    private const PRODUCT_SKU = 'GIBSON-LPSU25-B9GH3';

    private const PRODUCT_NAME = 'Gibson Les Paul Supreme, Blueberry Burst, Exclusive';

    private const PRODUCT_DESCRIPTION = 'La Les Paul Supreme con acabado exclusivo Blueberry Burst. Cuerpo de mahogany con tapa de arce AAA figurado, mástil de mahogany con perfil SlimTaper, diapasón de ebano con inlays Super Split Block mother-of-pearl. Pastillas Burstbucker Pro y Pro Plus humbuckers con coil-tap, phase y Pure Bypass switching. Incluye estuche hardshell.';

    private const BASE_PRICE_CENTS = 399900;

    private const VARIANT_SLUG = 'blueberry-burst';

    private const SIZE_1600 = '1600x1600';

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Gibson', 'gibson');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            $product = $this->ensureProduct(
                brandId: $brand->id,
                categoryId: $category->id,
                currencyId: $currency->id,
            );

            $variant = $this->ensureVariant(productId: $product->id);

            $this->syncProductImages(productId: $product->id);
            $this->syncStoreFrontImages(productId: $product->id);
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
        int $brandId,
        int $categoryId,
        int $currencyId,
    ): Product {
        return Product::query()->updateOrCreate(
            attributes: ['sku' => self::PRODUCT_SKU],
            values: [
                'name' => self::PRODUCT_NAME,
                'slug' => self::PRODUCT_SLUG,
                'description' => self::PRODUCT_DESCRIPTION,
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

    private function ensureVariant(int $productId): ProductVariant
    {
        return ProductVariant::query()->updateOrCreate(
            attributes: [
                'sku' => self::PRODUCT_SKU,
                'product_id' => $productId,
            ],
            values: [
                'name' => sprintf('%s — %s', self::PRODUCT_NAME, ucwords(str_replace('-', ' ', self::VARIANT_SLUG))),
                'price_cents' => self::BASE_PRICE_CENTS,
                'cost_cents' => (int) (self::BASE_PRICE_CENTS * 0.5),
                'is_active' => true,
            ],
        );
    }

    private function syncProductImages(int $productId): void
    {
        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->delete();

        $images = [
            ['index' => '01', 'is_primary' => true],
            ['index' => '02', 'is_primary' => false],
        ];

        foreach ($images as $idx => $img) {
            $imageUrl = $this->imageUrl(index: $img['index']);
            ProductImage::query()->create(attributes: [
                'product_id' => $productId,
                'image_url' => $imageUrl,
                'alt_text' => sprintf('%s — vista %s', self::VARIANT_SLUG, $img['index']),
                'is_primary' => $img['is_primary'],
                'sort_order' => $idx,
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

            $images = [
                ['index' => '01', 'is_primary' => true],
                ['index' => '02', 'is_primary' => false],
            ];

            foreach ($images as $idx => $img) {
                $imageUrl = $this->imageUrl(index: $img['index']);
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $imageUrl,
                    'alt_text' => sprintf('%s — vista %s', self::VARIANT_SLUG, $img['index']),
                    'is_primary' => $img['is_primary'],
                    'sort_order' => $idx,
                ]);
            }
        }
    }

    private function imageUrl(string $index): string
    {
        return sprintf(
            '/images/products/%s/%s/%s-%s-%s-%s.webp',
            self::PRODUCT_SLUG,
            self::VARIANT_SLUG,
            self::PRODUCT_SLUG,
            self::VARIANT_SLUG,
            self::SIZE_1600,
            $index,
        );
    }
}
