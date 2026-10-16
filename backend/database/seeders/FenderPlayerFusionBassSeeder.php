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
use App\Models\Stores\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder — Fender Limited Edition Player Fusion Precision Bass HH.
 *
 * Producto de referencia para el sprint de imagen PDP: se carga con
 * 2 variantes (Ebony Black + Halo Metallic) y 10 imágenes WebP
 * responsive (5 por variante, 4 tamaños cada una).
 *
 * Estructura de archivos (generados vía scripts/convert-product-images.sh):
 *   frontend/public/images/products/fender-player-fusion-bass-hh/
 *     source/                                       ← JPGs originales
 *     ebony-black/fender-player-fusion-bass-hh-ebony-black-{WxH}-{NN}.webp
 *     halo-metallic/fender-player-fusion-bass-hh-halo-metallic-{WxH}-{NN}.webp
 *
 * Naming algorithm aplicado al nombre:
 *   {product-slug}-{variant-slug}-{WxH}-{NN-zero-padded}.webp
 *
 * Tamaños generados: 1600x730, 1200x547, 800x365, 400x183 (alto proporcional
 * al aspect ratio 1600x730 = ~2.19:1 de las imágenes fuente).
 *
 * Estado actual (Oct 2026):
 *   - Solo registramos la IMAGEN PRINCIPAL por variante en la tabla
 *     product_images (la PDP hace render de la imagen 1600x730).
 *   - Las versiones responsive (1200/800/400) se sirven vía
 *     `<picture>` o `srcset` en el frontend.
 *
 * Idempotente: re-ejecutar actualiza las URLs y ordena sin duplicar.
 *
 * Uso:
 *   php artisan db:seed --class=FenderPlayerFusionBassSeeder
 */
final class FenderPlayerFusionBassSeeder extends Seeder
{
    private const PRODUCT_SLUG = 'fender-player-fusion-bass-hh';

    private const PRODUCT_NAME = 'Fender Limited Edition Player Fusion Precision Bass HH';

    private const PRODUCT_SKU = 'FND-LPFB4-HH-2026';

    private const PRODUCT_DESCRIPTION = 'Bajo eléctrico Precision Bass edición limitada con doble humbucker, mástil de maple, cuerpo de aliso y electrónica personalizada. Acabado Ultra Heavy Aged y pastillas Custombucker hand-wound.';

    /** Precio base en cents (USD). El storefront lo multiplica y formatea. */
    private const BASE_PRICE_CENTS = 179900;

    /** Imágenes por variante (índice zero-padded). */
    private const VARIANT_IMAGES = [
        'ebony-black' => [
            '01', '02', '03', '04', '05',
        ],
        'halo-metallic' => [
            '01', '02', '03', '04', '05',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Fender', 'fender');
            $category = $this->ensureCategory('Bajo eléctrico', 'bajo-electrico');
            $currency = $this->ensureCurrency('USD');

            $product = $this->ensureProduct(
                brandId: $brand->id,
                categoryId: $category->id,
                currencyId: $currency->id,
            );

            foreach (self::VARIANT_IMAGES as $variantSlug => $imageIndexes) {
                $variant = $this->ensureVariant(
                    productId: $product->id,
                    sku: sprintf('FND-LPFB4-HH-%s', strtoupper(str_replace('-', '', $variantSlug))),
                    name: sprintf('Player Fusion Precision Bass HH — %s', ucwords(str_replace('-', ' ', $variantSlug))),
                    priceCents: self::BASE_PRICE_CENTS,
                );

                $this->syncProductImages(
                    productId: $product->id,
                    variantSlug: $variantSlug,
                    imageIndexes: $imageIndexes,
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
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function ensureCategory(string $name, string $slug): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function ensureCurrency(string $code): Currency
    {
        return Currency::query()->updateOrCreate(
            ['code' => $code],
            ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true],
        );
    }

    private function ensureProduct(int $brandId, int $categoryId, int $currencyId): Product
    {
        return Product::query()->updateOrCreate(
            ['slug' => self::PRODUCT_SLUG],
            [
                'name' => self::PRODUCT_NAME,
                'sku' => self::PRODUCT_SKU,
                'description' => self::PRODUCT_DESCRIPTION,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'currency_id' => $currencyId,
                'base_price_cents' => self::BASE_PRICE_CENTS,
                'cost_cents' => (int) (self::BASE_PRICE_CENTS * 0.5),
                'is_active' => true,
                // La Voz Instrumentos vende mayoria nuevos (true) pero los
                // bajos Fender NO tienen tallas relevantes (4/4, 3/4, etc.
                // son irrelevantes para instrumentos eléctricos adultos).
                'has_condition' => true,
                'has_sizes' => false,
                'published_at' => now(),
            ],
        );
    }

    private function ensureVariant(int $productId, string $sku, string $name, int $priceCents): ProductVariant
    {
        return ProductVariant::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $productId,
                'name' => $name,
                'price_cents' => $priceCents,
                'cost_cents' => (int) ($priceCents * 0.5),
                'is_active' => true,
            ],
        );
    }

    /**
     * Sincroniza product_images del product con las URLs WebP.
     * La PDP renderiza la imagen 1600x730; las versiones responsive
     * (1200/800/400) se sirven vía <picture>/srcset en el frontend.
     */
    private function syncProductImages(int $productId, string $variantSlug, array $imageIndexes): void
    {
        // Borra imágenes previas de esta variante (idempotencia).
        // Asumimos que TODAS las imágenes cuyo alt_text empieza por "{variantSlug} —"
        // pertenecen a esta variante.
        $prefix = sprintf('%s — ', $variantSlug);
        ProductImage::query()
            ->where('product_id', $productId)
            ->where('alt_text', 'like', $prefix.'%')
            ->delete();

        foreach ($imageIndexes as $idx => $nn) {
            $imageUrl = $this->imageUrl($variantSlug, '1600x730', $nn);
            $alt = sprintf('%s — vista %s', $variantSlug, $nn);
            ProductImage::query()->create([
                'product_id' => $productId,
                'image_url' => $imageUrl,
                'alt_text' => $alt,
                'is_primary' => $idx === 0, // la primera imagen de cada variante es la principal
                'sort_order' => $idx,
            ]);
        }
    }

    /**
     * Sincroniza product_store_images para todas las tiendas donde
     * este producto está publicado. Mismas imágenes que product_images,
     * apuntando al CDN/frontend público del tenant.
     */
    private function syncStoreFrontImages(int $productId): void
    {
        $productStores = ProductStore::query()->where('product_id', $productId)->get();
        foreach ($productStores as $productStore) {
            // Borra imágenes previas del store para este product.
            ProductStoreImage::query()->where('product_store_id', $productStore->id)->delete();

            // Re-crea las imágenes para cada variante.
            foreach (self::VARIANT_IMAGES as $variantSlug => $imageIndexes) {
                foreach ($imageIndexes as $idx => $nn) {
                    $imageUrl = $this->imageUrl($variantSlug, '1600x730', $nn);
                    ProductStoreImage::query()->create([
                        'product_store_id' => $productStore->id,
                        'image_url' => $imageUrl,
                        'alt_text' => sprintf('%s — vista %s', $variantSlug, $nn),
                        'is_primary' => $idx === 0,
                        'sort_order' => $idx,
                    ]);
                }
            }
        }
    }

    /**
     * URL pública de la imagen WebP (sirve el frontend estático).
     * Formato: /images/products/{slug}/{variant}/{slug}-{variant}-{WxH}-{NN}.webp
     */
    private function imageUrl(string $variantSlug, string $size, string $nn): string
    {
        return sprintf(
            '/images/products/%s/%s/%s-%s-%s-%s.webp',
            self::PRODUCT_SLUG,
            $variantSlug,
            self::PRODUCT_SLUG,
            $variantSlug,
            $size,
            $nn,
        );
    }
}
