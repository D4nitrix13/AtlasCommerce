<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductRelationType;
use App\Http\Controllers\Api\Storefront\ProductRelationController;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductRelation;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\ProductStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contrato JSON del endpoint de relaciones del storefront.
 *
 * Se prueba sobre el controller (no sobre HTTP) a propósito: la
 * resolución de tenant por `store_domains` se lleva los tests por
 * delante cuando el host de la request no matchea, y ya está verificada
 * de punta a punta contra el backend real.
 *
 * Forma esperada:
 *   { "bought_together": [Product...], "you_may_also_like": [Product...] }
 * con cada producto serializado por `StorefrontProductResource`.
 */
#[Group('api')]
#[Group('catalog')]
#[Group('product-relations')]
final class ProductRelationControllerTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $source;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::query()->firstOrCreate(
            attributes: ['code' => 'NIO'],
            values: ['name' => 'NIO', 'symbol' => 'C$', 'is_primary' => true],
        );
        $this->store = Store::query()->firstOrCreate(
            attributes: ['slug' => 'panda-estampados'],
            values: ['name' => 'Panda', 'is_active' => true, 'currency_id' => $currency->id],
        );

        $category = \App\Models\Catalog\Category::query()->firstOrCreate(
            attributes: ['slug' => 'drinkware'],
            values: ['name' => 'Drinkware', 'is_active' => true],
        );

        $this->source = $this->makeProduct(
            slug: 'taza-ceramica',
            categoryId: $category->id,
            currencyId: $currency->id,
        );
    }

    /**
     * Alta de producto visible en la tienda del test.
     */
    private function makeProduct(string $slug, int $categoryId, int $currencyId, bool $visible = true): Product
    {
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'name' => $slug,
            'slug' => $slug,
            'sku' => strtoupper('TEST-'.$slug),
            'base_price_cents' => 1000,
            'is_active' => true,
            'published_at' => now(),
            'currency_id' => $currencyId,
        ]);

        ProductStore::query()->firstOrCreate(
            attributes: ['product_id' => $product->id, 'store_id' => $this->store->id],
            values: ['slug' => $product->slug, 'is_visible' => $visible],
        );

        return $product;
    }

    /**
     * Invoca el controller con el store resuelto, sin pasar por HTTP.
     *
     * @return array<string, mixed>
     */
    private function callController(string $slug): array
    {
        $request = Request::create(uri: '/api/v1/storefront/products/'.$slug.'/relations', method: 'GET');
        $request->attributes->set(key: 'store', value: $this->store);

        $response = (new ProductRelationController)->index(request: $request, slug: $slug);

        return $response->getData(true);
    }

    #[Test]
    public function it_returns_both_rails_with_storefront_product_payloads(): void
    {
        $related = $this->makeProduct(
            slug: 'libreta',
            categoryId: $this->source->category_id,
            currencyId: (int) $this->source->currency_id,
        );
        $crossSell = $this->makeProduct(
            slug: 'hoodie',
            categoryId: $this->source->category_id,
            currencyId: (int) $this->source->currency_id,
        );

        ProductRelation::query()->create([
            'product_id' => $this->source->id,
            'related_product_id' => $related->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);
        ProductRelation::query()->create([
            'product_id' => $this->source->id,
            'related_product_id' => $crossSell->id,
            'type' => ProductRelationType::YouMayAlsoLike->value,
            'position' => 0,
        ]);

        $payload = $this->callController(slug: 'taza-ceramica');

        $this->assertSame('libreta', $payload['bought_together'][0]['slug'] ?? null);
        $this->assertSame('hoodie', $payload['you_may_also_like'][0]['slug'] ?? null);
        // Shape del resource: al menos el contrato mínimo del storefront.
        $this->assertArrayHasKey(key: 'priceCents', array: $payload['bought_together'][0] ?? []);
        $this->assertArrayHasKey(key: 'imageUrl', array: $payload['bought_together'][0] ?? []);
    }

    #[Test]
    public function it_orders_each_rail_by_position(): void
    {
        $first = $this->makeProduct(
            slug: 'libreta',
            categoryId: $this->source->category_id,
            currencyId: (int) $this->source->currency_id,
        );
        $second = $this->makeProduct(
            slug: 'tote-bag',
            categoryId: $this->source->category_id,
            currencyId: (int) $this->source->currency_id,
        );

        // Se insertan desordenados a propósito: manda `position`.
        ProductRelation::query()->create([
            'product_id' => $this->source->id,
            'related_product_id' => $second->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 1,
        ]);
        ProductRelation::query()->create([
            'product_id' => $this->source->id,
            'related_product_id' => $first->id,
            'type' => ProductRelationType::BoughtTogether->value,
            'position' => 0,
        ]);

        $payload = $this->callController(slug: 'taza-ceramica');

        $this->assertSame(
            expected: ['libreta', 'tote-bag'],
            actual: array_column($payload['bought_together'] ?? [], 'slug'),
        );
    }

    #[Test]
    public function it_hides_relations_pointing_to_products_not_visible_in_store(): void
    {
        $hidden = $this->makeProduct(
            slug: 'libreta',
            categoryId: $this->source->category_id,
            currencyId: (int) $this->source->currency_id,
            visible: false,
        );
        $inactive = $this->makeProduct(
            slug: 'tote-bag',
            categoryId: $this->source->category_id,
            currencyId: (int) $this->source->currency_id,
        );
        $inactive->update(['is_active' => false]);

        foreach ([$hidden->id, $inactive->id] as $relatedId) {
            ProductRelation::query()->create([
                'product_id' => $this->source->id,
                'related_product_id' => $relatedId,
                'type' => ProductRelationType::BoughtTogether->value,
                'position' => 0,
            ]);
        }

        $payload = $this->callController(slug: 'taza-ceramica');

        $this->assertSame(expected: [], actual: $payload['bought_together'] ?? null);
    }

    #[Test]
    public function it_returns_empty_rails_for_a_product_without_relations(): void
    {
        $payload = $this->callController(slug: 'taza-ceramica');

        $this->assertSame(expected: [], actual: $payload['bought_together'] ?? null);
        $this->assertSame(expected: [], actual: $payload['you_may_also_like'] ?? null);
    }
}
