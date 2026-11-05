<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\ProductRelationType;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductRelation;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\PandaKitsuneCatalogSeeder;
use Database\Seeders\ProductRelationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Relaciones curadas del catálogo Panda/Kitsune.
 *
 * Casos del negocio que fija este test:
 *   - El seeder siembra los pares curados por slug y los mantiene
 *     DENTRO de una tienda (Panda nunca se relaciona con Kitsune).
 *   - Es idempotente: correrlo dos veces no duplica filas.
 */
#[Group('catalog')]
#[Group('seeder')]
#[Group('product-relations')]
final class ProductRelationsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mismo arranque que `PandaKitsuneCatalogSeederTest`: los stores
        // vienen de `tenants:bootstrap`; el test los crea para no depender
        // de ese comando.
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

        $this->seed(class: AttributeDefinitionSeeder::class);
        $this->seed(class: PandaKitsuneCatalogSeeder::class);
    }

    /**
     * Resuelve el id de un producto por su slug.
     */
    private function idOf(string $slug): int
    {
        return Product::query()
            ->where(column: 'slug', operator: '=', value: $slug)
            ->firstOrFail()
            ->id;
    }

    #[Test]
    public function it_seeds_curated_relations_within_each_store(): void
    {
        $this->seed(class: ProductRelationsSeeder::class);

        // Panda: taza + libreta comprados juntos.
        $this->assertDatabaseHas(table: 'product_relations', data: [
            'product_id' => $this->idOf(slug: 'taza-ceramica'),
            'related_product_id' => $this->idOf(slug: 'libreta'),
            'type' => ProductRelationType::BoughtTogether->value,
        ]);

        // Kitsune: nendoroid + llavero comprados juntos.
        $this->assertDatabaseHas(table: 'product_relations', data: [
            'product_id' => $this->idOf(slug: 'nendoroid'),
            'related_product_id' => $this->idOf(slug: 'llavero-acrylic'),
            'type' => ProductRelationType::BoughtTogether->value,
        ]);

        // Nunca cruza tiendas: Panda → Kitsune no existe.
        $this->assertDatabaseMissing(table: 'product_relations', data: [
            'product_id' => $this->idOf(slug: 'taza-ceramica'),
            'related_product_id' => $this->idOf(slug: 'nendoroid'),
        ]);
    }

    #[Test]
    public function it_is_idempotent_when_seeded_twice(): void
    {
        $this->seed(class: ProductRelationsSeeder::class);
        $first = ProductRelation::query()->count();

        $this->seed(class: ProductRelationsSeeder::class);

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, ProductRelation::query()->count());
    }
}
