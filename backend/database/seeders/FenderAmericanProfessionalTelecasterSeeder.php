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
 * Seeder — Fender American Professional Telecaster, 3-Color Sunburst.
 *
 * Producto de ejemplo para la PDP con imágenes reales descargadas del
 * CDN oficial de Fender. Se carga con 1 variante (3-Color Sunburst) y
 * 2 imágenes (front + back) en landscape 1600×525 (3.05:1).
 *
 * Estructura de archivos:
 *   frontend/public/images/products/fender-american-professional-telecaster/3-color-sunburst/
 *     fender-american-professional-telecaster-3-color-sunburst-1600x525-01.webp  (front)
 *     fender-american-professional-telecaster-3-color-sunburst-1600x525-02.webp  (back)
 *
 * Convenciones de la galería PDP:
 *   - LANDSCAPE 1600×525 (Fender AmPro source es 1600×525)
 *   - El `imageSrcset` del frontend deriva el aspect del URL — Gibson
 *     SQUARE 1600×1600 y Fender LANDSCAPE 1600×525 coexisten en
 *     el mismo gallery sin configuración por producto.
 *
 * Idempotente: re-ejecutar actualiza sin duplicar.
 *
 * Variantes:
 *   Esta seed cubre SOLO el color "3-Color Sunburst". Las otras 6
 *   variantes (Black / Natural / Oxblood / Dark Night / Aged Cherry
 *   Burst / Butterscotch Blonde) son productos separados en el
 *   catálogo de Fender y requerirían sus propios seeders si se
 *   quieren seedear individualmente.
 *
 * Uso:
 *   php artisan db:seed --class=FenderAmericanProfessionalTelecasterSeeder
 */
final class FenderAmericanProfessionalTelecasterSeeder extends Seeder
{
    private const PRODUCT_SLUG = 'fender-american-professional-telecaster-3-color-sunburst';

    private const VARIANT_SLUG = '3-color-sunburst';

    private const PRODUCT_SKU = 'FEND-AMPRO-TEL-3CS';

    private const PRODUCT_NAME = 'Fender American Professional Telecaster, 3-Color Sunburst';

    private const PRODUCT_DESCRIPTION = 'La American Professional Telecaster combina más de setenta años de innovación con pickups V-Mod III single-coil, puente top-load/string-through con ClassicGear™ Compensated Brass Bullet™ Saddles, y perfil de mástil Deep "C" con bordes rolled.';

    private const BASE_PRICE_CENTS = 169900;

    /** Solo registramos la imagen 1600×525. Las versiones responsive
     *  (ancho menor) se sirven vía imageSrcset en el frontend. */
    private const SIZE_1600 = '1600x525';

    /** Índices de archivo: 01 = front (frt_1_rr.png), 02 = back (bck_1_rl.png).
     *  (Patrón Fender: {SKU7}_fen_ins_{view}_{idx}_{orientation}.png) */
    private const FRONT_INDEX = '01';

    private const BACK_INDEX = '02';

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Guitarra eléctrica', 'guitarra-electrica');
            $currency = $this->ensureCurrency('USD');

            $product = $this->ensureProduct(
                brandId: $brand->id,
                categoryId: $category->id,
                currencyId: $currency->id,
            );

            $variant = $this->ensureVariant(
                productId: $product->id,
            );

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

    /**
     * Inserta front + back. Idempotente.
     */
    private function syncProductImages(int $productId): void
    {
        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->delete();

        $images = [
            ['index' => self::FRONT_INDEX, 'is_primary' => true],
            ['index' => self::BACK_INDEX, 'is_primary' => false],
        ];

        foreach ($images as $idx => $img) {
            $imageUrl = $this->imageUrl(size: self::SIZE_1600, index: $img['index']);
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

            foreach ([self::FRONT_INDEX => true, self::BACK_INDEX => false] as $index => $isPrimary) {
                $imageUrl = $this->imageUrl(size: self::SIZE_1600, index: $index);
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $imageUrl,
                    'alt_text' => sprintf('%s — vista %s', self::VARIANT_SLUG, $index),
                    'is_primary' => $isPrimary,
                    'sort_order' => $isPrimary ? 0 : 1,
                ]);
            }
        }
    }

    private function imageUrl(string $size, string $index): string
    {
        return sprintf(
            '/images/products/%s/%s/%s-%s-%s-%s.webp',
            self::PRODUCT_SLUG,
            self::VARIANT_SLUG,
            self::PRODUCT_SLUG,
            self::VARIANT_SLUG,
            $size,
            $index,
        );
    }
}
