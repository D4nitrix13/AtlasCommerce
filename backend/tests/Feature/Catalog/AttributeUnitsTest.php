<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttribute;
use App\Models\Catalog\ProductAttributeValue;
use App\Models\Catalog\ProductSize;
use App\Models\Catalog\ProductVariant;
use App\Models\Catalog\VariantAttributeValue;
use App\Models\Settings\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contrato de atributos de producto con unidad (opción A confirmada:
 * talla/capacidad como variante, atributos en EAV).
 *
 * Casos que fija este test, pedidos por el negocio:
 *   - La taza se define por su capacidad en onzas (11 oz / 15 oz).
 *   - Gorra y camisa por su talla (CH/M/G/XG).
 *   - Cada tipo de producto trae datos extra propios (material, peso…).
 *   - Ningún producto está obligado a tener galería de imágenes.
 */
#[Group('catalog')]
#[Group('attributes')]
final class AttributeUnitsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'currency_id' => Currency::factory()->primary(),
        ]);
    }

    // ── attributes: unidad, opciones y alcance ────────────────────────

    #[Test]
    public function it_persists_attribute_with_unit(): void
    {
        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Capacidad',
            'slug' => 'capacity',
            'type' => 'number',
            'unit' => 'oz',
        ]);

        $this->assertDatabaseHas(table: 'attributes', data: [
            'id' => $attribute->id,
            'slug' => 'capacity',
            'type' => 'number',
            'unit' => 'oz',
        ]);
    }

    #[Test]
    public function it_persists_attribute_with_enum_options(): void
    {
        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Talla',
            'slug' => 'size',
            'type' => 'select',
            'options' => ['CH', 'M', 'G', 'XG'],
        ]);

        $this->assertDatabaseHas(table: 'attributes', data: [
            'id' => $attribute->id,
            'slug' => 'size',
            'type' => 'select',
        ]);
        $this->assertSame(
            ['CH', 'M', 'G', 'XG'],
            $attribute->fresh()->options,
        );
    }

    #[Test]
    public function it_persists_attribute_scoped_to_categories(): void
    {
        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Capacidad',
            'slug' => 'capacity',
            'type' => 'number',
            'unit' => 'oz',
            'applies_to' => ['drinkware'],
        ]);

        $this->assertSame(['drinkware'], $attribute->fresh()->applies_to);
    }

    // ── product_sizes: valor numérico + unidad ────────────────────────

    #[Test]
    public function it_persists_size_with_numeric_value_and_unit(): void
    {
        // Caso del negocio: taza de 11 onzas.
        $size = ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'label' => '11 oz',
            'value' => 11,
            'unit' => 'oz',
            'position' => 1,
        ]);

        $this->assertDatabaseHas(table: 'product_sizes', data: [
            'id' => $size->id,
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'value' => 11,
            'unit' => 'oz',
        ]);
    }

    #[Test]
    public function it_persists_apparel_size_without_numeric_value(): void
    {
        // Caso del negocio: talla de camisa/gorra — sin valor numérico.
        $size = ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => 'm',
            'label' => 'M',
            'position' => 2,
        ]);

        $this->assertDatabaseHas(table: 'product_sizes', data: [
            'id' => $size->id,
            'product_id' => $this->product->id,
            'slug' => 'm',
            'unit' => null,
        ]);
        $this->assertNull($size->fresh()->value);
    }

    #[Test]
    public function it_links_variant_to_a_size_with_capacity(): void
    {
        $size = ProductSize::query()->create(attributes: [
            'product_id' => $this->product->id,
            'slug' => '15-oz',
            'label' => '15 oz',
            'value' => 15,
            'unit' => 'oz',
            'position' => 1,
        ]);

        $variant = ProductVariant::query()->create(attributes: [
            'product_id' => $this->product->id,
            'name' => 'Taza 15 oz Blanca',
            'sku' => 'TAZA-15-BLANCO',
            'price_cents' => 1400,
            'size_id' => $size->id,
        ]);

        $this->assertDatabaseHas(table: 'product_variants', data: [
            'id' => $variant->id,
            'size_id' => $size->id,
        ]);
        $this->assertSame(15, (int) $variant->size->value);
        $this->assertSame('oz', $variant->size->unit);
    }

    // ── attribute_product: valores tipados ────────────────────────────

    #[Test]
    public function it_persists_product_attribute_value_with_number(): void
    {
        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Peso',
            'slug' => 'weight',
            'type' => 'number',
            'unit' => 'kg',
        ]);

        $value = ProductAttributeValue::query()->create(attributes: [
            'product_id' => $this->product->id,
            'attribute_id' => $attribute->id,
            'value_number' => 0.35,
        ]);

        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'id' => $value->id,
            'attribute_id' => $attribute->id,
            'value_number' => 0.35,
        ]);
    }

    #[Test]
    public function it_persists_product_attribute_value_with_boolean(): void
    {
        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Apto para microondas',
            'slug' => 'microwave_safe',
            'type' => 'boolean',
        ]);

        $value = ProductAttributeValue::query()->create(attributes: [
            'product_id' => $this->product->id,
            'attribute_id' => $attribute->id,
            'value_boolean' => true,
        ]);

        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'id' => $value->id,
            'value_boolean' => true,
        ]);
    }

    // ── attribute_variant: valores tipados ────────────────────────────

    #[Test]
    public function it_persists_variant_attribute_value_with_number(): void
    {
        $attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Capacidad',
            'slug' => 'capacity',
            'type' => 'number',
            'unit' => 'oz',
        ]);

        $variant = ProductVariant::query()->create(attributes: [
            'product_id' => $this->product->id,
            'name' => 'Taza 11 oz Blanca',
            'sku' => 'TAZA-11-BLANCO',
            'price_cents' => 1200,
        ]);

        $value = VariantAttributeValue::query()->create(attributes: [
            'product_variant_id' => $variant->id,
            'attribute_id' => $attribute->id,
            'value_number' => 11,
        ]);

        $this->assertDatabaseHas(table: 'attribute_variant', data: [
            'id' => $value->id,
            'product_variant_id' => $variant->id,
            'value_number' => 11,
        ]);
    }

    // ── galería opcional ──────────────────────────────────────────────

    #[Test]
    public function it_allows_product_without_images(): void
    {
        $this->assertCount(0, $this->product->images);
        $this->assertDatabaseCount(table: 'product_images', count: 0);
    }
}
