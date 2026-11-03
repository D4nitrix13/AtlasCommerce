<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Storefront;

use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttribute;
use App\Models\Catalog\ProductAttributeValue;
use App\Models\Catalog\ProductSize;
use App\Models\Catalog\ProductVariant;
use App\Models\Settings\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contrato del storefront API para tallas y atributos.
 *
 * La API tiene que servir los datos variables que la UI necesita:
 * capacidad de la taza en onzas, talla de ropa, y los atributos EAV
 * (material, cuidados). Sin esto el frontend no puede renderizar el
 * picker de tallas ni la ficha técnica.
 *
 * Se prueba sobre el resource (no sobre HTTP) a propósito: la
 * resolución de tenant por `store_domains` se lleva los tests por
 * delante cuando el host de la request no matchea, y ya está verificada
 * de punta a punta contra el backend real.
 */
#[Group('api')]
#[Group('catalog')]
final class StorefrontProductSizesTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::query()->firstOrCreate(
            attributes: ['code' => 'USD'],
            values: ['name' => 'US Dollar', 'symbol' => '$', 'is_primary' => true],
        );
        $category = Category::query()->firstOrCreate(
            attributes: ['slug' => 'drinkware'],
            values: ['name' => 'Drinkware', 'is_active' => true],
        );
        $brand = Brand::query()->firstOrCreate(
            attributes: ['slug' => 'panda'],
            values: ['name' => 'Panda', 'is_active' => true],
        );

        $this->product = Product::query()->create([
            'sku' => 'PANDA-TAZA-TEST',
            'name' => 'Taza cerámica',
            'slug' => 'taza-ceramica-test',
            'description' => 'Taza de 11 oz.',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_id' => $currency->id,
            'base_price_cents' => 1200,
            'has_sizes' => true,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $size = ProductSize::query()->create([
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'label' => '11 oz',
            'value' => 11,
            'unit' => 'oz',
            'position' => 1,
        ]);

        ProductVariant::query()->create([
            'product_id' => $this->product->id,
            'name' => 'Taza Blanca 11 oz',
            'sku' => 'PANDA-TAZA-TEST-BLANCO-11-OZ',
            'price_cents' => 1200,
            'finish_slug' => 'blanco',
            'finish_label' => 'Blanco',
            'finish_hex' => '#F7F4EF',
            'condition' => 'nuevo',
            'stock' => 40,
            'size_id' => $size->id,
        ]);

        $material = ProductAttribute::query()->firstOrCreate(
            attributes: ['slug' => 'material'],
            values: ['name' => 'Material', 'type' => 'select', 'unit' => null],
        );
        ProductAttributeValue::query()->create([
            'product_id' => $this->product->id,
            'attribute_id' => $material->id,
            'value' => 'Cerámica',
        ]);
    }

    private function payload(): array
    {
        $this->product->load([
            'variants',
            'variants.size',
            'sizes',
            'attributeValues',
            'attributeValues.attribute',
        ]);

        return (new StorefrontProductResource($this->product))
            ->toArray(Request::create('/api/v1/storefront/products/taza-ceramica-test'));
    }

    #[Test]
    public function it_exposes_sizes_with_value_and_unit(): void
    {
        $sizes = $this->payload()['sizes'] ?? [];

        $this->assertSame('11-oz', $sizes[0]['slug'] ?? null);
        $this->assertEqualsWithDelta(11, (float) ($sizes[0]['value'] ?? 0), 0.001);
        $this->assertSame('oz', $sizes[0]['unit'] ?? null);
    }

    #[Test]
    public function it_exposes_variant_size_context(): void
    {
        $variant = $this->payload()['variants'][0] ?? [];

        $this->assertSame('11-oz', $variant['sizeSlug'] ?? null);
        $this->assertEqualsWithDelta(11, (float) ($variant['sizeValue'] ?? 0), 0.001);
        $this->assertSame('oz', $variant['sizeUnit'] ?? null);
    }

    #[Test]
    public function it_exposes_eav_attributes(): void
    {
        $material = collect($this->payload()['attributes'] ?? [])->firstWhere('slug', 'material');

        $this->assertNotNull($material);
        $this->assertSame('Cerámica', $material['value'] ?? null);
    }
}
