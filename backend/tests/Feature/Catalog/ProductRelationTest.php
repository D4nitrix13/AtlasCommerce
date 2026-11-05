<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\ProductRelationType;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductRelation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integridad de `product_relations` — la tabla que cura los rieles de
 * recomendación de la PDP ("comprados juntos" / "también te puede gustar").
 *
 * Casos del negocio que fija este test:
 *   - Un par (origen, relacionado, tipo) es único: no se pueden
 *     duplicar recomendaciones del mismo tipo.
 *   - El mismo par puede convivir en dos tipos distintos.
 *   - `ofType()` filtra por tipo y ordena por `position`.
 *   - Borrar un producto (hard delete) arrastra sus relaciones en
 *     ambos extremos del FK.
 */
#[Group('catalog')]
#[Group('product-relations')]
final class ProductRelationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Product $related;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = $this->makeProduct(slug: 'taza-ceramica', sku: 'TEST-TAZA');
        $this->related = $this->makeProduct(slug: 'libreta', sku: 'TEST-LIBRETA');
    }

    /**
     * Producto mínimo válido para tocar la tabla `product_relations`.
     */
    private function makeProduct(string $slug, string $sku): Product
    {
        $category = \App\Models\Catalog\Category::query()->firstOrCreate(
            attributes: ['slug' => 'test-cat'],
            values: ['name' => 'Test Cat', 'is_active' => true],
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $slug,
            'slug' => $slug,
            'sku' => $sku,
            'base_price_cents' => 1000,
            'is_active' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Inserta una relación cruda con los defaults de la tabla.
     *
     * @return array<string, mixed>
     */
    private function relationData(
        ProductRelationType $type = ProductRelationType::BoughtTogether,
        int $position = 0,
    ): array {
        return [
            'product_id' => $this->product->id,
            'related_product_id' => $this->related->id,
            'type' => $type->value,
            'position' => $position,
        ];
    }

    #[Test]
    public function it_rejects_duplicate_relation_for_same_product_related_and_type(): void
    {
        ProductRelation::query()->create($this->relationData());

        // Savepoint interno: la violación no aborta la transacción que
        // `RefreshDatabase` envuelve al test.
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            ProductRelation::query()->create($this->relationData());
        });
    }

    #[Test]
    public function it_allows_same_pair_with_a_different_type(): void
    {
        ProductRelation::query()->create($this->relationData());
        ProductRelation::query()->create($this->relationData(
            type: ProductRelationType::YouMayAlsoLike,
        ));

        $this->assertDatabaseCount(table: 'product_relations', count: 2);
    }

    #[Test]
    public function it_filters_by_type_and_orders_by_position(): void
    {
        $third = $this->makeProduct(slug: 'tote-bag', sku: 'TEST-TOTE');

        ProductRelation::query()->create($this->relationData(position: 2));
        ProductRelation::query()->create([
            'product_id' => $this->product->id,
            'related_product_id' => $third->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);
        ProductRelation::query()->create($this->relationData(
            type: ProductRelationType::YouMayAlsoLike,
            position: 0,
        ));

        $rows = ProductRelation::query()
            ->ofType(ProductRelationType::BoughtTogether)
            ->get();

        $this->assertSame(
            expected: [$third->id, $this->related->id],
            actual: $rows->pluck('related_product_id')->all(),
        );
    }

    #[Test]
    public function it_cascades_delete_when_source_product_is_deleted(): void
    {
        ProductRelation::query()->create($this->relationData());

        $this->product->forceDelete();

        $this->assertDatabaseCount(table: 'product_relations', count: 0);
    }

    #[Test]
    public function it_cascades_delete_when_related_product_is_deleted(): void
    {
        ProductRelation::query()->create($this->relationData());

        $this->related->forceDelete();

        $this->assertDatabaseCount(table: 'product_relations', count: 0);
    }
}
