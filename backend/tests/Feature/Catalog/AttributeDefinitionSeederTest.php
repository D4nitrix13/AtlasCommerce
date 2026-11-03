<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Catalog\ProductAttribute;
use App\Models\Catalog\ProductSize;
use Database\Seeders\AttributeDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Diccionario de atributos del catálogo.
 *
 * Fija el vocabulario con el que cada tipo de producto describe sus
 * datos variables — el pedido del negocio: la taza por su capacidad en
 * onzas, la camisa y la gorra por su talla, y los datos extra propios
 * de cada tipo (material, peso).
 */
#[Group('catalog')]
#[Group('attributes')]
#[Group('seeder')]
final class AttributeDefinitionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: AttributeDefinitionSeeder::class);
    }

    #[Test]
    public function it_seeds_capacity_in_ounces_for_drinkware(): void
    {
        $capacity = ProductAttribute::query()->where('slug', 'capacity')->firstOrFail();

        $this->assertSame('number', $capacity->type);
        $this->assertSame('oz', $capacity->unit);
        $this->assertContains('drinkware', $capacity->applies_to);
        $this->assertTrue($capacity->is_filterable);
    }

    #[Test]
    public function it_seeds_size_as_select_with_apparel_options(): void
    {
        $size = ProductAttribute::query()->where('slug', 'size')->firstOrFail();

        $this->assertSame('select', $size->type);
        $this->assertSame(['CH', 'M', 'G', 'XG'], $size->options);
    }

    #[Test]
    public function it_seeds_material_and_weight(): void
    {
        $this->assertDatabaseHas(table: 'attributes', data: [
            'slug' => 'material',
            'type' => 'select',
        ]);
        $this->assertDatabaseHas(table: 'attributes', data: [
            'slug' => 'weight',
            'type' => 'number',
            'unit' => 'kg',
        ]);
    }

    #[Test]
    public function it_seeds_care_flags_as_boolean(): void
    {
        $microwave = ProductAttribute::query()->where('slug', 'microwave_safe')->firstOrFail();

        $this->assertSame('boolean', $microwave->type);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $before = ProductAttribute::query()->count();

        $this->seed(class: AttributeDefinitionSeeder::class);
        $this->seed(class: AttributeDefinitionSeeder::class);

        $this->assertSame($before, ProductAttribute::query()->count());
    }

    // ── los casos del negocio, de punta a punta ──────────────────────

    #[Test]
    public function it_expresses_mug_capacity_as_size_with_value_and_unit(): void
    {
        // Taza de 11 onzas: la capacidad es la talla de la variante.
        $product = \App\Models\Catalog\Product::factory()->create();

        $size = ProductSize::query()->create(attributes: [
            'product_id' => $product->id,
            'slug' => '11-oz',
            'label' => '11 oz',
            'value' => 11,
            'unit' => 'oz',
        ]);

        $this->assertSame(11.0, (float) $size->value);
        $this->assertSame('oz', $size->unit);
    }

    #[Test]
    public function it_expresses_apparel_size_without_numeric_value(): void
    {
        // Camisa talla M: la talla no se mide, se enumera.
        $product = \App\Models\Catalog\Product::factory()->create();

        $size = ProductSize::query()->create(attributes: [
            'product_id' => $product->id,
            'slug' => 'm',
            'label' => 'M',
        ]);

        $this->assertNull($size->value);
        $this->assertNull($size->unit);
    }
}
