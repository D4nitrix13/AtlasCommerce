<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttributeValue;
use App\Models\Catalog\ProductSize;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Illuminate\Database\Seeder;

/**
 * Catálogo de Panda Estampados y Kitsune, con sus datos variables.
 *
 * Cada tipo de producto trae lo suyo: la taza se define por su
 * capacidad en onzas (11 oz / 15 oz), la playera y el hoodie por su
 * talla (CH/M/G/XG). Ambas dimensiones viven como `product_sizes` y la
 * variante compraable las referencia por `size_id` — opción A del
 * diseño: talla/capacidad es variante, no producto aparte.
 *
 * Los atributos restantes (material, cuidados) van por EAV
 * (`attribute_product`), ya definidos por `AttributeDefinitionSeeder`.
 *
 * Idempotente: usa `updateOrCreate` sobre slugs, así que se puede
 * correr varias veces sin duplicar.
 */
class PandaKitsuneCatalogSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     */
    public function run(): void
    {
        $this->seedTenant(tenant: 'panda-estampados', brandName: 'Panda Estampados', products: $this->pandaProducts());
        $this->seedTenant(tenant: 'kitsune', brandName: 'Kitsune', products: $this->kitsuneProducts());
    }

    /**
     * Siembra los productos de un tenant.
     *
     * @param  array<int, array<string, mixed>>  $products
     */
    private function seedTenant(string $tenant, string $brandName, array $products): void
    {
        $store = Store::query()->where('slug', $tenant)->first();
        if ($store === null) {
            $this->command?->warn("Tenant {$tenant} no existe — saltando catálogo.");
            return;
        }

        $currency = Currency::query()->find($store->currency_id)
            ?? Currency::query()->where('is_primary', true)->firstOrFail();
        $brand = Brand::query()->firstOrCreate(
            attributes: ['slug' => str_replace(' ', '-', strtolower($brandName))],
            values: ['name' => $brandName, 'is_active' => true],
        );

        foreach ($products as $data) {
            $category = Category::query()->firstOrCreate(
                attributes: ['slug' => $data['category_slug']],
                values: ['name' => $data['category'], 'is_active' => true],
            );

            $hasSizes = array_key_exists('sizes', $data) && count($data['sizes']) > 0;

            $product = Product::query()->updateOrCreate(
                attributes: ['sku' => strtoupper("{$tenant}-{$data['slug']}")],
                values: [
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'description' => $data['description'],
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'currency_id' => $currency->id,
                    'base_price_cents' => $data['base_price_cents'],
                    'has_sizes' => $hasSizes,
                    'is_active' => true,
                    'published_at' => now(),
                ],
            );

            $sizeIds = $this->seedSizes($product, $data['sizes'] ?? []);
            $this->seedVariants($product, $data, $sizeIds, $currency);
            $this->seedAttributes($product, $data['attributes'] ?? []);
        }
    }

    /**
     * Crea/actualiza las tallas o capacidades del producto.
     *
     * @param  array<int, array<string, mixed>>  $sizes
     * @return array<string, int> slug → id
     */
    private function seedSizes(Product $product, array $sizes): array
    {
        $ids = [];
        foreach ($sizes as $position => $size) {
            $row = ProductSize::query()->updateOrCreate(
                attributes: ['product_id' => $product->id, 'slug' => $size['slug']],
                values: [
                    'label' => $size['label'],
                    'value' => $size['value'] ?? null,
                    'unit' => $size['unit'] ?? null,
                    'position' => $position + 1,
                ],
            );
            $ids[$size['slug']] = $row->id;
        }
        return $ids;
    }

    /**
     * Crea/actualiza las variantes comprables (acabado × talla).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $sizeIds
     */
    private function seedVariants(Product $product, array $data, array $sizeIds, Currency $currency): void
    {
        $finishes = $data['finishes'] ?? [];
        $sizeSlugs = $data['sizes'] ?? [];

        // Sin tallas hay una sola variante por acabado; con tallas, una
        // por combinación acabado × talla.
        $combos = [];
        foreach ($finishes as $finish) {
            if (count($sizeSlugs) === 0) {
                $combos[] = ['finish' => $finish, 'size' => null];
                continue;
            }
            foreach ($sizeSlugs as $size) {
                $combos[] = ['finish' => $finish, 'size' => $size];
            }
        }

        foreach ($combos as $combo) {
            /** @var array<string, mixed> $finish */
            $finish = $combo['finish'];
            /** @var array<string, mixed>|null $size */
            $size = $combo['size'];

            $suffix = $size === null ? '' : '-'.$size['slug'];
            ProductVariant::query()->updateOrCreate(
                attributes: [
                    'product_id' => $product->id,
                    'sku' => strtoupper("{$product->sku}-{$finish['slug']}{$suffix}"),
                ],
                values: [
                    'name' => trim("{$product->name} {$finish['label']}".($size === null ? '' : " {$size['label']}")),
                    'price_cents' => $data['base_price_cents'] + (int) ($size['price_delta_cents'] ?? 0),
                    'finish_slug' => $finish['slug'],
                    'finish_label' => $finish['label'],
                    'finish_hex' => $finish['hex'] ?? null,
                    'condition' => 'nuevo',
                    'stock' => 40,
                    'size_id' => $size === null ? null : $sizeIds[$size['slug']],
                    'handedness' => null,
                ],
            );
        }
    }

    /**
     * Asigna los atributos EAV del producto.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedAttributes(Product $product, array $attributes): void
    {
        foreach ($attributes as $slug => $value) {
            $attribute = \App\Models\Catalog\ProductAttribute::query()->where('slug', $slug)->first();
            if ($attribute === null) {
                continue;
            }

            $isBool = is_bool($value);
            $isNumeric = is_numeric($value) && ! $isBool;

            ProductAttributeValue::query()->updateOrCreate(
                attributes: ['product_id' => $product->id, 'attribute_id' => $attribute->id],
                values: [
                    'value' => is_bool($value) ? null : (string) $value,
                    'value_number' => $isNumeric ? (float) $value : null,
                    'value_boolean' => $isBool ? $value : null,
                ],
            );
        }
    }

    /**
     * Piezas de Panda Estampados (estampado sobre prenda y merch).
     *
     * @return array<int, array<string, mixed>>
     */
    private function pandaProducts(): array
    {
        $apparelSizes = [
            ['slug' => 'ch', 'label' => 'CH'],
            ['slug' => 'm', 'label' => 'M'],
            ['slug' => 'g', 'label' => 'G'],
            ['slug' => 'xg', 'label' => 'XG'],
        ];

        return [
            [
                'slug' => 'taza-ceramica',
                'name' => 'Taza cerámica',
                'description' => 'Taza de cerámica para estampado. Disponible en 11 oz y 15 oz.',
                'category' => 'Drinkware',
                'category_slug' => 'drinkware',
                'base_price_cents' => 1200,
                'sizes' => [
                    ['slug' => '11-oz', 'label' => '11 oz', 'value' => 11, 'unit' => 'oz'],
                    ['slug' => '15-oz', 'label' => '15 oz', 'value' => 15, 'unit' => 'oz', 'price_delta_cents' => 200],
                ],
                'finishes' => [
                    ['slug' => 'blanco', 'label' => 'Blanco', 'hex' => '#F7F4EF'],
                    ['slug' => 'negro', 'label' => 'Negro', 'hex' => '#1C1917'],
                ],
                'attributes' => [
                    'material' => 'Cerámica',
                    'microwave_safe' => true,
                    'dishwasher_safe' => true,
                ],
            ],
            [
                'slug' => 'playera-manga-corta',
                'name' => 'Playera manga corta',
                'description' => 'Playera de algodón para evento o equipo. Cuello redondo.',
                'category' => 'Textil',
                'category_slug' => 'textil',
                'base_price_cents' => 1800,
                'sizes' => $apparelSizes,
                'finishes' => [
                    ['slug' => 'negro', 'label' => 'Negro', 'hex' => '#1C1917'],
                    ['slug' => 'blanco', 'label' => 'Blanco', 'hex' => '#F7F4EF'],
                ],
                'attributes' => ['material' => 'Algodón'],
            ],
            [
                'slug' => 'hoodie',
                'name' => 'Hoodie con capucha',
                'description' => 'Sudadera con capucha. Estampado centrado, sin relieve.',
                'category' => 'Textil',
                'category_slug' => 'textil',
                'base_price_cents' => 4200,
                'sizes' => $apparelSizes,
                'finishes' => [
                    ['slug' => 'negro', 'label' => 'Negro', 'hex' => '#1C1917'],
                ],
                'attributes' => ['material' => 'Algodón'],
            ],
            [
                'slug' => 'tote-bag',
                'name' => 'Tote bag',
                'description' => 'Bolsa de canvas reutilizable. Estampado a un lado.',
                'category' => 'Accesorio',
                'category_slug' => 'accesorio',
                'base_price_cents' => 1500,
                'finishes' => [
                    ['slug' => 'blanco', 'label' => 'Blanco', 'hex' => '#F7F4EF'],
                ],
                'attributes' => ['material' => 'Canvas'],
            ],
            [
                'slug' => 'gorra-trucker',
                'name' => 'Gorra trucker',
                'description' => 'Gorra trucker con cierre ajustable. Estampado frontal.',
                'category' => 'Accesorio',
                'category_slug' => 'accesorio',
                'base_price_cents' => 1600,
                'sizes' => [
                    ['slug' => 'unitalla', 'label' => 'Unitalla'],
                ],
                'finishes' => [
                    ['slug' => 'negro', 'label' => 'Negro', 'hex' => '#1C1917'],
                ],
                'attributes' => ['material' => 'Poliéster'],
            ],
            [
                'slug' => 'libreta',
                'name' => 'Libreta',
                'description' => 'Libreta de bolsillo con tapa dura. Estampado en tapa.',
                'category' => 'Accesorio',
                'category_slug' => 'accesorio',
                'base_price_cents' => 900,
                'finishes' => [
                    ['slug' => 'blanco', 'label' => 'Blanco', 'hex' => '#F7F4EF'],
                ],
                'attributes' => ['material' => 'Papel kraft'],
            ],
        ];
    }

    /**
     * Piezas de Kitsune (figuras, manga y merch otaku).
     *
     * @return array<int, array<string, mixed>>
     */
    private function kitsuneProducts(): array
    {
        return [
            [
                'slug' => 'nendoroid',
                'name' => 'Nendoroid',
                'description' => 'Figura chibi articulada con accesorios intercambiables.',
                'category' => 'Figuras',
                'category_slug' => 'figuras',
                'base_price_cents' => 6500,
                'finishes' => [['slug' => 'original', 'label' => 'Original', 'hex' => '#F472B6']],
                'attributes' => ['material' => 'PVC'],
            ],
            [
                'slug' => 'figura-escala-1-7',
                'name' => 'Figura escala 1/7',
                'description' => 'Figura de escala con base temática y detalle de pintura.',
                'category' => 'Figuras',
                'category_slug' => 'figuras',
                'base_price_cents' => 18900,
                'finishes' => [['slug' => 'original', 'label' => 'Original', 'hex' => '#7C3AED']],
                'attributes' => ['material' => 'PVC'],
            ],
            [
                'slug' => 'mango-tomo',
                'name' => 'Manga tomo suelto',
                'description' => 'Tomo de manga en formato japonés, tapa blanda.',
                'category' => 'Manga',
                'category_slug' => 'manga',
                'base_price_cents' => 1200,
                'finishes' => [['slug' => 'original', 'label' => 'Original', 'hex' => '#EF4444']],
                'attributes' => ['material' => 'Papel kraft'],
            ],
            [
                'slug' => 'llavero-acrylic',
                'name' => 'Llavero acrylic',
                'description' => 'Llavero de acrylic con impresión a doble cara.',
                'category' => 'Accesorios',
                'category_slug' => 'accesorios',
                'base_price_cents' => 800,
                'finishes' => [['slug' => 'original', 'label' => 'Original', 'hex' => '#F472B6']],
                'attributes' => ['material' => 'PVC'],
            ],
            [
                'slug' => 'playera-anime',
                'name' => 'Playera anime',
                'description' => 'Playera con estampado temático. Algodón suave.',
                'category' => 'Accesorios',
                'category_slug' => 'accesorios',
                'base_price_cents' => 2200,
                'sizes' => [
                    ['slug' => 'm', 'label' => 'M'],
                    ['slug' => 'g', 'label' => 'G'],
                    ['slug' => 'xg', 'label' => 'XG'],
                ],
                'finishes' => [['slug' => 'negro', 'label' => 'Negro', 'hex' => '#1C1917']],
                'attributes' => ['material' => 'Algodón'],
            ],
            [
                'slug' => 'taza-tematica',
                'name' => 'Taza temática',
                'description' => 'Taza de cerámica con diseño impreso envolvente.',
                'category' => 'Drinkware',
                'category_slug' => 'drinkware',
                'base_price_cents' => 1400,
                'sizes' => [
                    ['slug' => '11-oz', 'label' => '11 oz', 'value' => 11, 'unit' => 'oz'],
                ],
                'finishes' => [['slug' => 'original', 'label' => 'Original', 'hex' => '#FB923C']],
                'attributes' => [
                    'material' => 'Cerámica',
                    'microwave_safe' => true,
                    'dishwasher_safe' => false,
                ],
            ],
        ];
    }
}
