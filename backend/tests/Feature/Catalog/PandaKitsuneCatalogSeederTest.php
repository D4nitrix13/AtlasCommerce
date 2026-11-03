<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttributeValue;
use App\Models\Catalog\ProductSize;
use App\Models\Catalog\ProductVariant;
use Database\Seeders\PandaKitsuneCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catálogo de Panda Estampados y Kitsune sembrado con sus datos
 * variables: capacidades de taza en onzas y tallas de ropa.
 *
 * Casos del negocio que fija este test:
 *   - La taza se define por su capacidad (11 oz / 15 oz) como variante.
 *   - La playera y el hoodie por su talla (CH/M/G/XG).
 *   - Cada pieza trae sus atributos (material, cuidados).
 */
#[Group('catalog')]
#[Group('seeder')]
#[Group('tenant')]
final class PandaKitsuneCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los stores vienen de `tenants:bootstrap`; el test los crea para
        // no depender de ese comando.
        foreach ([['slug' => 'panda-estampados', 'code' => 'NIO'], ['slug' => 'kitsune', 'code' => 'USD']] as $tenant) {
            $currency = \App\Models\Settings\Currency::query()->firstOrCreate(
                attributes: ['code' => $tenant['code']],
                values: ['name' => $tenant['code'], 'symbol' => '$', 'is_primary' => false],
            );
            \App\Models\Stores\Store::query()->firstOrCreate(
                attributes: ['slug' => $tenant['slug']],
                values: ['name' => $tenant['slug'], 'is_active' => true, 'currency_id' => $currency->id],
            );
        }

        $this->seed(class: \Database\Seeders\AttributeDefinitionSeeder::class);
        $this->seed(class: PandaKitsuneCatalogSeeder::class);
    }

    #[Test]
    public function it_seeds_panda_and_kitsune_products(): void
    {
        $this->assertDatabaseHas(table: 'products', data: ['slug' => 'taza-ceramica']);
        $this->assertDatabaseHas(table: 'products', data: ['slug' => 'playera-manga-corta']);
        $this->assertDatabaseHas(table: 'products', data: ['slug' => 'nendoroid']);
    }

    #[Test]
    public function it_seeds_mug_capacity_as_sizes_with_value_and_unit(): void
    {
        $mug = Product::query()->where('slug', 'taza-ceramica')->firstOrFail();

        $sizes = ProductSize::query()
            ->where('product_id', $mug->id)
            ->orderBy('value')
            ->get();

        $this->assertCount(2, $sizes);
        $this->assertSame('11-oz', $sizes[0]->slug);
        $this->assertEquals(11, $sizes[0]->value);
        $this->assertSame('oz', $sizes[0]->unit);
        $this->assertSame('15-oz', $sizes[1]->slug);
        $this->assertEquals(15, $sizes[1]->value);
        $this->assertSame('oz', $sizes[1]->unit);
    }

    #[Test]
    public function it_seeds_apparel_sizes_without_numeric_value(): void
    {
        $shirt = Product::query()->where('slug', 'playera-manga-corta')->firstOrFail();

        $sizes = ProductSize::query()
            ->where('product_id', $shirt->id)
            ->orderBy('position')
            ->get();

        $this->assertSame(['ch', 'm', 'g', 'xg'], $sizes->pluck('slug')->all());
        $this->assertTrue($sizes->every(fn (ProductSize $s): bool => $s->value === null && $s->unit === null));
    }

    #[Test]
    public function it_links_variants_to_their_sizes(): void
    {
        $mug = Product::query()->where('slug', 'taza-ceramica')->firstOrFail();

        $variants = ProductVariant::query()
            ->where('product_id', $mug->id)
            ->with('size')
            ->get();

        $this->assertNotEmpty($variants);
        $this->assertTrue(
            $variants->contains(fn (ProductVariant $v): bool => $v->size?->slug === '11-oz'),
        );
    }

    #[Test]
    public function it_seeds_mug_material_and_care_attributes(): void
    {
        $mug = Product::query()->where('slug', 'taza-ceramica')->firstOrFail();

        $material = ProductAttributeValue::query()
            ->where('product_id', $mug->id)
            ->whereHas('attribute', fn ($q): mixed => $q->where('slug', 'material'))
            ->first();

        $this->assertNotNull($material);
        $this->assertSame('Cerámica', $material->value);

        $microwave = ProductAttributeValue::query()
            ->where('product_id', $mug->id)
            ->whereHas('attribute', fn ($q): mixed => $q->where('slug', 'microwave_safe'))
            ->first();

        $this->assertNotNull($microwave);
        $this->assertTrue((bool) $microwave->value_boolean);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $products = Product::query()->count();
        $sizes = ProductSize::query()->count();

        $this->seed(class: PandaKitsuneCatalogSeeder::class);

        $this->assertSame($products, Product::query()->count());
        $this->assertSame($sizes, ProductSize::query()->count());
    }
}
