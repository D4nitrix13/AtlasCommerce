<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductRelationType;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductRelation;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de `product_relations` (Frecuentemente Comprados Juntos /
 * También Te Puede Gustar) y del endpoint
 * `GET /api/v1/storefront/products/{slug}/relations`.
 *
 * Cubre:
 *  - constraint UNIQUE (product_id, related_product_id, type).
 *  - scope ofType() ordenado por position.
 *  - cascade delete de relaciones al borrar un producto.
 *  - shape del endpoint: `{bought_together: [], you_may_also_like: []}`
 *    con payloads StorefrontProductResource, solo productos visibles
 *    de la tienda actual, ordenados por position.
 *  - idempotencia del ProductRelationsSeeder (dos corridas = mismas filas).
 */
#[Group('storefront')]
#[Group('relations')]
final class StorefrontProductRelationsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->category = Category::factory()->create(attributes: [
            'name' => 'Bebidas',
            'slug' => 'drinkware',
        ]);
        $this->brand = Brand::factory()->create(attributes: [
            'name' => 'Panda',
            'slug' => 'panda',
        ]);

        $this->store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['slug' => 'panda-estampados']);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.test',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeProduct(array $attributes = []): Product
    {
        $slug = $attributes['slug'] ?? 'taza-ceramica';
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'name' => 'Taza ceramica',
            'slug' => $slug,
            'description' => 'Taza de ceramica para estampado.',
            'base_price_cents' => 1200,
            'is_active' => true,
            'has_condition' => false,
            'has_sizes' => false,
            'is_exclusive' => false,
        ], $attributes));

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    #[Test]
    public function unique_constraint_rejects_duplicate_product_relation_type_triples(): void
    {
        $product = $this->makeProduct();
        $related = $this->makeProduct(['slug' => 'libreta']);

        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $related->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);

        $this->expectException(QueryException::class);

        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $related->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 1,
        ]);
    }

    #[Test]
    public function of_type_scope_orders_by_position_and_filters_by_type(): void
    {
        $product = $this->makeProduct();
        $first = $this->makeProduct(['slug' => 'libreta']);
        $second = $this->makeProduct(['slug' => 'tote-bag']);

        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $second->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 1,
        ]);
        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $first->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);
        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $first->id,
            'type' => ProductRelationType::YouMayAlsoLike->value,
            'position' => 0,
        ]);

        $rows = ProductRelation::query()
            ->ofType(ProductRelationType::BoughtTogether)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame($first->id, $rows[0]->related_product_id);
        $this->assertSame($second->id, $rows[1]->related_product_id);
    }

    #[Test]
    public function relations_cascade_delete_with_their_product(): void
    {
        $product = $this->makeProduct();
        $related = $this->makeProduct(['slug' => 'libreta']);

        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $related->id,
            'type' => ProductRelationType::YouMayAlsoLike->value,
            'position' => 0,
        ]);

        // Product usa SoftDeletes: el delete() blandito deja las filas
        // (el endpoint las filtra igual por el scope global). El cascade
        // FK dispara en el borrado físico — que exige sacar antes el
        // pivot product_stores (FK RESTRICT propia).
        ProductStore::query()
            ->where('product_id', $related->id)
            ->delete();
        $related->forceDelete();

        $this->assertSame(0, ProductRelation::query()->count());
    }

    #[Test]
    public function endpoint_returns_curated_relations_in_position_order(): void
    {
        $product = $this->makeProduct();
        $bundleFirst = $this->makeProduct(['slug' => 'libreta']);
        $bundleSecond = $this->makeProduct(['slug' => 'tote-bag']);
        $alike = $this->makeProduct(['slug' => 'hoodie']);

        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $bundleSecond->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 1,
        ]);
        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $bundleFirst->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);
        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $alike->id,
            'type' => ProductRelationType::YouMayAlsoLike->value,
            'position' => 0,
        ]);

        $response = $this->getJson(
            uri: 'http://panda-estampados.test/api/v1/storefront/products/taza-ceramica/relations',
        );

        $response->assertOk();
        $response->assertJsonPath(
            path: 'bought_together.0.slug',
            expect: 'libreta',
        );
        $response->assertJsonPath(
            path: 'bought_together.1.slug',
            expect: 'tote-bag',
        );
        $response->assertJsonPath(
            path: 'you_may_also_like.0.slug',
            expect: 'hoodie',
        );
    }

    #[Test]
    public function endpoint_excludes_relations_not_visible_in_the_current_store(): void
    {
        $product = $this->makeProduct();
        $hidden = $this->makeProduct(['slug' => 'libreta']);

        ProductStore::query()
            ->where('product_id', $hidden->id)
            ->update(['is_visible' => false]);

        ProductRelation::query()->create(attributes: [
            'product_id' => $product->id,
            'related_product_id' => $hidden->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);

        $response = $this->getJson(
            uri: 'http://panda-estampados.test/api/v1/storefront/products/taza-ceramica/relations',
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'bought_together');
        $response->assertJsonCount(0, 'you_may_also_like');
    }

    #[Test]
    public function endpoint_returns_empty_rails_when_the_product_has_no_relations(): void
    {
        $this->makeProduct();

        $response = $this->getJson(
            uri: 'http://panda-estampados.test/api/v1/storefront/products/taza-ceramica/relations',
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'bought_together');
        $response->assertJsonCount(0, 'you_may_also_like');
    }

    #[Test]
    public function product_relations_seeder_is_idempotent(): void
    {
        // Slugs que el seeder usa como origen (ver ProductRelationsSeeder).
        $this->makeProduct(['slug' => 'taza-ceramica']);
        $this->makeProduct(['slug' => 'libreta']);
        $this->makeProduct(['slug' => 'tote-bag']);

        $this->seed(class: \Database\Seeders\ProductRelationsSeeder::class);

        $afterFirst = ProductRelation::query()->count();
        $this->assertGreaterThan(0, $afterFirst);

        $this->seed(class: \Database\Seeders\ProductRelationsSeeder::class);

        $this->assertSame($afterFirst, ProductRelation::query()->count());
    }
}
