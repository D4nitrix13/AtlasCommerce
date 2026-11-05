<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Storefront\RecentlyViewedProduct;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de los endpoints públicos de productos vistos recientemente:
 *   - POST /api/v1/storefront/recently-viewed
 *   - GET  /api/v1/storefront/recently-viewed
 *
 * Cubre:
 *  - POST registra la visita (identidad guest o user) y responde 204.
 *  - GET devuelve los productos nuevos-primero con la shape del
 *    storefront (`StorefrontProductResource`).
 *  - Producto desconocido / oculto en POST → 404.
 *  - Sin identidad: GET devuelve `data: []`, POST devuelve 422.
 *  - `exclude` acepta slug o id.
 */
#[Group('storefront')]
#[Group('recently-viewed')]
final class StorefrontRecentlyViewedTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    private User $user;

    private Customer $customer;

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

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->user->id]);
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

    private function recentlyViewedUrl(string $query = ''): string
    {
        return 'http://panda-estampados.test/api/v1/storefront/recently-viewed'.$query;
    }

    #[Test]
    public function it_records_a_guest_view_and_returns_204(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: [
                'product_id' => $product->id,
                'guest_token' => 'guest-token-abc',
            ],
        );

        $response->assertNoContent();

        $this->assertDatabaseHas(table: 'recently_viewed_products', data: [
            'user_id' => null,
            'guest_token' => 'guest-token-abc',
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function it_records_the_view_under_the_authenticated_user(): void
    {
        $product = $this->makeProduct();

        Sanctum::actingAs(user: $this->user);

        $response = $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: [
                'product_id' => $product->id,
                'guest_token' => 'guest-token-ignorado',
            ],
        );

        $response->assertNoContent();

        // Con sesión activa manda la identidad user; el guest_token del
        // body se descarta (queda NULL en la fila).
        $this->assertDatabaseHas(table: 'recently_viewed_products', data: [
            'user_id' => $this->user->id,
            'guest_token' => null,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function it_returns_the_recently_viewed_products_newest_first_in_storefront_shape(): void
    {
        $first = $this->makeProduct(['slug' => 'taza-ceramica']);
        $second = $this->makeProduct(['slug' => 'libreta', 'name' => 'Libreta', 'base_price_cents' => 2500]);

        $base = now();
        $this->travelTo($base->copy()->subMinutes(5));
        $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $first->id, 'guest_token' => 'guest-token-abc'],
        )->assertNoContent();

        $this->travelTo($base);
        $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $second->id, 'guest_token' => 'guest-token-abc'],
        )->assertNoContent();

        $response = $this->getJson(
            uri: $this->recentlyViewedUrl('?guest_token=guest-token-abc'),
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath(path: 'data.0.slug', expect: 'libreta');
        $response->assertJsonPath(path: 'data.1.slug', expect: 'taza-ceramica');
        // Shape de StorefrontProductResource (no filas crudas del modelo).
        $response->assertJsonPath(path: 'data.0.name', expect: 'Libreta');
        $response->assertJsonPath(path: 'data.0.priceCents', expect: 2500);
        $response->assertJsonPath(path: 'data.0.category', expect: 'drinkware');
    }

    #[Test]
    public function it_returns_404_for_an_unknown_product_on_post(): void
    {
        $response = $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: [
                'product_id' => 999999,
                'guest_token' => 'guest-token-abc',
            ],
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_a_hidden_product_on_post(): void
    {
        $hidden = $this->makeProduct(['slug' => 'libreta']);

        ProductStore::query()
            ->where('product_id', $hidden->id)
            ->update(['is_visible' => false]);

        $response = $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: [
                'product_id' => $hidden->id,
                'guest_token' => 'guest-token-abc',
            ],
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_empty_data_when_no_identity_is_supplied(): void
    {
        $product = $this->makeProduct();

        // Existe historial de OTRA identidad (el user); sin auth y sin
        // guest_token el GET no tiene identidad propia y no debe filtrarlo.
        RecentlyViewedProduct::query()->create(attributes: [
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'viewed_at' => now(),
        ]);

        $response = $this->getJson(uri: $this->recentlyViewedUrl());

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_returns_422_when_a_guest_posts_without_identity(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $product->id],
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_returns_422_when_product_id_is_missing_on_post(): void
    {
        $response = $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['guest_token' => 'guest-token-abc'],
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_excludes_the_product_resolved_from_the_exclude_slug(): void
    {
        $first = $this->makeProduct(['slug' => 'taza-ceramica']);
        $second = $this->makeProduct(['slug' => 'libreta']);

        $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $first->id, 'guest_token' => 'guest-token-abc'],
        )->assertNoContent();
        $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $second->id, 'guest_token' => 'guest-token-abc'],
        )->assertNoContent();

        $response = $this->getJson(
            uri: $this->recentlyViewedUrl('?guest_token=guest-token-abc&exclude=taza-ceramica'),
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.slug', expect: 'libreta');
    }

    #[Test]
    public function it_excludes_the_product_resolved_from_the_exclude_id(): void
    {
        $first = $this->makeProduct(['slug' => 'taza-ceramica']);
        $second = $this->makeProduct(['slug' => 'libreta']);

        $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $first->id, 'guest_token' => 'guest-token-abc'],
        )->assertNoContent();
        $this->postJson(
            uri: $this->recentlyViewedUrl(),
            data: ['product_id' => $second->id, 'guest_token' => 'guest-token-abc'],
        )->assertNoContent();

        $response = $this->getJson(
            uri: $this->recentlyViewedUrl('?guest_token=guest-token-abc&exclude='.$first->id),
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.slug', expect: 'libreta');
    }
}
