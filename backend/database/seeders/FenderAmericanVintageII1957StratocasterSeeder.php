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
 * Seeder — Fender American Vintage II 1957 Stratocaster, Black.
 *
 * Producto de recon round 1 (Oct 2026) — descargado del CDN oficial
 * de Fender. Imagen front es específica del SKU Black (0110232806);
 * imagen back es COMPARTIDA entre variantes del modelo (probada
 * con la 2-Color Sunburst 0110232803 que da la misma vista trasera
 * del instrumento — Fender usa el mismo back photo entre colors).
 *
 * Estructura de archivos:
 *   frontend/public/images/products/fender-american-vintage-ii-1957-stratocaster/black/
 *     fender-american-vintage-ii-1957-stratocaster-black-1600x518-01.webp  (front)
 *     fender-american-vintage-ii-1957-stratocaster-black-1600x523-02.webp  (back, hash from 2CS SKU)
 *
 * Convenciones de la galería PDP:
 *   - LANDSCAPE 1600×525 (3.05:1)
 *   - El `imageSrcset` del frontend deriva el aspect del URL — Gibson
 *     SQUARE 1600×1600 y Fender LANDSCAPE 1600×525 coexisten en
 *     el mismo gallery sin configuración por producto.
 *
 * Idempotente: re-ejecutar actualiza sin duplicar.
 *
 * Uso:
 *   php artisan db:seed --class=FenderAmericanVintageII1957StratocasterSeeder
 */
final class FenderAmericanVintageII1957StratocasterSeeder extends Seeder
{
    private const PRODUCT_SLUG = 'fender-american-vintage-ii-1957-stratocaster';

    private const VARIANT_SLUG = 'black';

    private const PRODUCT_SKU = 'FEND-AMVII-57STR-2806';

    private const PRODUCT_NAME = 'Fender American Vintage II 1957 Stratocaster, Black';

    private const PRODUCT_DESCRIPTION = 'Reproducción de la Stratocaster de 1957 con perfil de mástil "V" original, mástil de maple con radio 7.25", trémolo sincronizado Pure Vintage, y pickups Pure Vintage 57 Strat single-coil. Acabado nitrocelulósico gloss sobre cuerpo de alder.';

    private const BASE_PRICE_CENTS = 239900;

    /** Solo registramos la imagen 1600×525. Las versiones responsive
     *  (ancho menor) se sirven vía imageSrcset en el frontend. */
    private const SIZE_1600 = '1600x525';

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
