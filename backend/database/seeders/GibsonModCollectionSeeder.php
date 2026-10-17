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
 * Seeder — Gibson Mod™ Collection + Custom Select (10 guitarras eléctricas).
 *
 * Cubre la fase 1 del seed pipeline de imágenes de Gibson
 * (recon Oct 2026). Cada producto es la combinación única
 * `modelo + finish` — Gibson expone cada variant como un producto
 * separado en `https://www.gibson.com/es/products/{handle}`.
 *
 * Estructura de archivos (generados vía pipeline download+convert):
 *   frontend/public/images/products/{slug}/{variant}/
 *     {slug}-{variant}-front-1600x1600-03.webp  ← galería principal
 *     {slug}-{variant}-back-1600x1600-04.webp   ← galería secundaria
 *
 * Convenciones de la galería PDP:
 *   - SQUARE 1600×1600 (Gibson Mod Collection source es 1600×1600)
 *   - Las 2 imágenes (front + back) se sirven desde product_images.
 *     Las versiones responsive 1200/800/400 se derivan vía
 *     `imageSrcset(url)` en ProductDetailGallery.astro (deriva aspect
 *     del URL base, no hardcoded).
 *
 * Idempotente: re-ejecutar actualiza sin duplicar.
 *
 * Uso:
 *   php artisan db:seed --class=GibsonModCollectionSeeder
 */
final class GibsonModCollectionSeeder extends Seeder
{
    private const CURRENCY_CODE = 'USD';

    /**
     * Catálogo seedeado. Cada entry es un PRODUCT (modelo+finish) de Gibson.
     * Usar `priceCents` consistente con la gama (~US$1600-$4500).
     */
    private const PRODUCTS = [
        [
            'slug' => 'gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle',
            'sku' => 'GIBSON-LPC68PSL-WHITE-SPARKLE',
            'name' => 'Gibson Custom 1968 Les Paul Custom Reissue, Gloss, White Sparkle',
            'description' => 'Reissue Custom Shop de la histórica Les Paul Custom 1968. Construcción mahogany con tapa de arce, binding múltiple, pastillas humbucker.',
            'priceCents' => 449900,
            'variantSlug' => 'gloss-white-sparkle',
            'variantName' => 'Gloss, White Sparkle',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-es-335-smooth-sepia',
            'sku' => 'GIBSON-MOD-ES335-SMOOTH-SEPIA',
            'name' => 'Gibson Mod™ Collection ES-335, Smooth Sepia',
            'description' => 'ES-335 semisólida en acabado Smooth Sepia. Mástil de mahogany, pickups MHS, stopbar.',
            'priceCents' => 299900,
            'variantSlug' => 'smooth-sepia',
            'variantName' => 'Smooth Sepia',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-sg-standard-holy-shift',
            'sku' => 'GIBSON-MOD-SGSTD-HOLY-SHIFT',
            'name' => 'Gibson Mod™ Collection SG Standard, Holy Shift',
            'description' => 'SG Standard con acabado Holy Shift exclusivo del Mod Collection. Mástil de mahogany, pastillas 490R/490T.',
            'priceCents' => 189900,
            'variantSlug' => 'holy-shift',
            'variantName' => 'Holy Shift',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-les-paul-standard-60s-hornet-burst',
            'sku' => 'GIBSON-MOD-LPSTD60S-HORNET',
            'name' => 'Gibson Mod™ Collection Les Paul Standard 60s, Hornet Burst',
            'description' => 'Les Paul Standard 60s con tapa de arce figurado en acabado Hornet Burst. Burstbucker 60s, Mástil 60s slim taper.',
            'priceCents' => 269900,
            'variantSlug' => 'hornet-burst',
            'variantName' => 'Hornet Burst',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-sg-modern-tenacity-blue',
            'sku' => 'GIBSON-MOD-SGMOD-TENACITY',
            'name' => 'Gibson Mod™ Collection SG Modern, Tenacity Blue',
            'description' => 'SG Modern con acabado Tenacity Blue. Mástil de maple roastado, pickups Burstbucker Pro, push-pull coil splits.',
            'priceCents' => 229900,
            'variantSlug' => 'tenacity-blue',
            'variantName' => 'Tenacity Blue',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-es-339-green-meme',
            'sku' => 'GIBSON-MOD-ES339-GREEN-MEME',
            'name' => 'Gibson Mod™ Collection ES-339, Green Meme',
            'description' => 'ES-339 en exclusivo acabado Green Meme. Construcción centerblock semisólida, Burstbucker Pro.',
            'priceCents' => 279900,
            'variantSlug' => 'green-meme',
            'variantName' => 'Green Meme',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-flying-v-80s-boisterous-blue',
            'sku' => 'GIBSON-MOD-FV80S-BOISTEROUS',
            'name' => 'Gibson Mod™ Collection Flying V 80s, Boisterous Blue',
            'description' => 'Flying V 80s Limited Run con acabado Boisterous Blue. Mástil de maple, pastillas Burstbucker 3.',
            'priceCents' => 219900,
            'variantSlug' => 'boisterous-blue',
            'variantName' => 'Boisterous Blue',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-les-paul-junior-tropicalia',
            'sku' => 'GIBSON-MOD-LPJUN-TROPICALIA',
            'name' => 'Gibson Mod™ Collection Les Paul Junior, Tropicalia',
            'description' => 'Les Paul Junior con acabado Tropicalia exclusivo. Cuerpo mahogany single-cut, P-90 dogear.',
            'priceCents' => 159900,
            'variantSlug' => 'tropicalia',
            'variantName' => 'Tropicalia',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite',
            'sku' => 'GIBSON-MOD-LPCUST70S-NNITE',
            'name' => 'Gibson Mod™ Collection Les Paul Custom 70s, Nighty-Nite Graphite',
            'description' => 'Les Paul Custom 70s en exclusivo acabado Nighty-Nite Graphite. Construcción mahogany, binding 5-ply, T-top pickups.',
            'priceCents' => 329900,
            'variantSlug' => 'nighty-nite-graphite',
            'variantName' => 'Nighty-Nite Graphite',
            'category' => 'guitarra-electrica',
        ],
        [
            'slug' => 'gibson-mod-collection-flying-v-80s-flame-job-hot-rod',
            'sku' => 'GIBSON-MOD-FV80S-FLAME',
            'name' => 'Gibson Mod™ Collection Flying V 80s, Flame Job Hot Rod',
            'description' => 'Flying V 80s con tapa de arce flameado en acabado Flame Job Hot Rod. Burstbucker 3, push-pull coil splits.',
            'priceCents' => 249900,
            'variantSlug' => 'flame-job-hot-rod',
            'variantName' => 'Flame Job Hot Rod',
            'category' => 'guitarra-electrica',
        ],
    ];

    /**
     * Tamaños de imagen. Convention: cada imagen existe en 4 widths
     * (1600/1200/800/400). Solo registramos la 1600×1600 (principal)
     * en la tabla — `imageSrcset` en el frontend deriva las demás.
     */
    private const SIZE_1600 = '1600x1600';

    /**
     * Índices de imagen que matchean los archivos descargados:
     *   front-{idx}.png   →  idx = "3" (la toma "_3_Front.png")
     *   back-{idx}.png    →  idx = "4" (la toma "_4_Back.png")
     * (Verificada manualmente en el recon pipeline — Shopify
     * numera sus vistas _1_Body, _2_BackBody, _3_Front, _4_Back, etc.)
     */
    private const FRONT_INDEX = '03';

    private const BACK_INDEX = '04';

    public function run(): void
    {
        DB::transaction(function (): void {
            $brand = $this->ensureBrand('Gibson', 'gibson');
            $currency = $this->ensureCurrency(self::CURRENCY_CODE);

            foreach (self::PRODUCTS as $productData) {
                $category = $this->ensureCategory(
                    name: match ($productData['category']) {
                        'guitarra-electrica' => 'Guitarra eléctrica',
                        default => 'Guitarra eléctrica',
                    },
                    slug: $productData['category'],
                );

                $product = $this->ensureProduct(
                    brandId: $brand->id,
                    categoryId: $category->id,
                    currencyId: $currency->id,
                    sku: $productData['sku'],
                    slug: $productData['slug'],
                    name: $productData['name'],
                    description: $productData['description'],
                    basePriceCents: $productData['priceCents'],
                );

                $variant = $this->ensureVariant(
                    productId: $product->id,
                    sku: $productData['sku'],
                    name: sprintf('%s — %s', $productData['name'], $productData['variantName']),
                    priceCents: $productData['priceCents'],
                );

                $this->syncProductImages(
                    productId: $product->id,
                    productSlug: $productData['slug'],
                    variantSlug: $productData['variantSlug'],
                );

                $this->syncStoreFrontImages(
                    productId: $product->id,
                    productSlug: $productData['slug'],
                    variantSlug: $productData['variantSlug'],
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
        int $brandId,
        int $categoryId,
        int $currencyId,
        string $sku,
        string $slug,
        string $name,
        string $description,
        int $basePriceCents,
    ): Product {
        return Product::query()->updateOrCreate(
            attributes: ['sku' => $sku],
            values: [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'currency_id' => $currencyId,
                'base_price_cents' => $basePriceCents,
                'cost_cents' => (int) ($basePriceCents * 0.5),
                'is_active' => true,
                // Guitarras eléctricas adultas son full-size por default
                // y La Voz vende casi todo nuevo (true) — excepciones
                // (bajos, children's) se manejan en seeders específicos.
                'has_condition' => false,
                'has_sizes' => false,
                'published_at' => now(),
            ],
        );
    }

    private function ensureVariant(
        int $productId,
        string $sku,
        string $name,
        int $priceCents,
    ): ProductVariant {
        return ProductVariant::query()->updateOrCreate(
            attributes: ['sku' => $sku],
            values: [
                'product_id' => $productId,
                'name' => $name,
                'price_cents' => $priceCents,
                'cost_cents' => (int) ($priceCents * 0.5),
                'is_active' => true,
            ],
        );
    }

    /**
     * Inserta 2 imágenes (front + back) por variante.
     * Idempotente: borra imágenes previas con el mismo alt_text antes
     * de re-crear para reflejar cambios de URL/path.
     */
    private function syncProductImages(
        int $productId,
        string $productSlug,
        string $variantSlug,
    ): void {
        $prefix = $variantSlug.' — ';
        ProductImage::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->where(column: 'alt_text', operator: 'like', value: $prefix.'%')
            ->delete();

        $images = [
            ['index' => self::FRONT_INDEX, 'is_primary' => true],
            ['index' => self::BACK_INDEX, 'is_primary' => false],
        ];

        foreach ($images as $idx => $img) {
            $imageUrl = $this->imageUrl(
                slug: $productSlug,
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

    private function syncStoreFrontImages(
        int $productId,
        string $productSlug,
        string $variantSlug,
    ): void {
        $productStores = ProductStore::query()
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->get();

        foreach ($productStores as $productStore) {
            ProductStoreImage::query()
                ->where(column: 'product_store_id', operator: '=', value: $productStore->id)
                ->delete();

            foreach ([self::FRONT_INDEX => true, self::BACK_INDEX => false] as $index => $isPrimary) {
                $imageUrl = $this->imageUrl(
                    slug: $productSlug,
                    variantSlug: $variantSlug,
                    size: self::SIZE_1600,
                    index: $index,
                );
                ProductStoreImage::query()->create(attributes: [
                    'product_store_id' => $productStore->id,
                    'image_url' => $imageUrl,
                    'alt_text' => sprintf('%s — vista %s', $variantSlug, $index),
                    'is_primary' => $isPrimary,
                    'sort_order' => $isPrimary ? 0 : 1,
                ]);
            }
        }
    }

    /**
     * URL pública WebP. Formato:
     *   /images/products/{slug}/{variant}/{slug}-{variant}-{WxH}-{NN}.webp
     */
    private function imageUrl(
        string $slug,
        string $variantSlug,
        string $size,
        string $index,
    ): string {
        return sprintf(
            '/images/products/%s/%s/%s-%s-%s-%s.webp',
            $slug,
            $variantSlug,
            $slug,
            $variantSlug,
            $size,
            $index,
        );
    }
}
