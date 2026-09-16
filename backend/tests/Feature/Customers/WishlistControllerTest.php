<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Auth\User;
use App\Models\Carts\Wishlist;
use App\Models\Carts\WishlistItem;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD de wishlist del cliente (RF-CART-013).
 *
 * Cubre:
 *  - 401 sin token.
 *  - Listado sólo propias listas.
 *  - Store + is_default exclusivo (desmarca las demás).
 *  - Show / Update (no aplica — name inmutable en MVP) / Destroy.
 *  - Add item idempotente.
 *  - Remove item + cross-lista 404.
 *  - Cross-customer 404.
 */
#[Group('customers')]
#[Group('wishlist')]
final class WishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Wishlist $wishlist;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->for(
            Currency::factory()->primary()->create(),
            'currency',
        )->create(['slug' => 'panda']);
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $this->customer->stores()->attach($this->store->id);

        $this->product = Product::factory()->for($this->store->currency, 'currency')->create();

        $this->wishlist = Wishlist::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'name' => 'Favoritos',
            'is_default' => true,
        ]);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[TestAttr]
    public function it_requires_authentication(): void
    {
        $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/wishlists')
            ->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_lists_only_own_wishlists(): void
    {
        $otherCustomer = Customer::factory()->create();
        Wishlist::factory()->create(['customer_id' => $otherCustomer->id, 'store_id' => $this->store->id]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/wishlists');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.id', expect: $this->wishlist->id);
    }

    #[TestAttr]
    public function it_stores_wishlist(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/account/wishlists',
            data: ['name' => 'Navidad'],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.name', expect: 'Navidad');
    }

    #[TestAttr]
    public function it_store_with_is_default_true_demotes_existing(): void
    {
        $this->assertTrue((bool) $this->wishlist->is_default);

        Sanctum::actingAs($this->user);

        $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/account/wishlists',
            data: ['name' => 'Nueva por defecto', 'is_default' => true],
        )->assertCreated();

        $this->assertFalse((bool) $this->wishlist->fresh()->is_default);
    }

    #[TestAttr]
    public function it_shows_wishlist(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/v1/account/wishlists/{$this->wishlist->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Favoritos');
    }

    #[TestAttr]
    public function it_destroys_wishlist(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(uri: "http://panda-estampados.com/api/v1/account/wishlists/{$this->wishlist->id}")
            ->assertNoContent();

        $this->assertSoftDeleted(table: 'wishlists', data: ['id' => $this->wishlist->id]);
    }

    #[TestAttr]
    public function it_adds_item_idempotently(): void
    {
        Sanctum::actingAs($this->user);

        $first = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/account/wishlists/{$this->wishlist->id}/items",
            data: ['product_id' => $this->product->id],
        );
        $first->assertCreated();

        // Segundo add: mismo producto → no duplica, mismo id.
        $second = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/account/wishlists/{$this->wishlist->id}/items",
            data: ['product_id' => $this->product->id],
        );
        $second->assertCreated();

        $this->assertSame(1, $this->wishlist->items()->count());
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
    }

    #[TestAttr]
    public function it_removes_item(): void
    {
        $item = WishlistItem::factory()->create([
            'wishlist_id' => $this->wishlist->id,
            'product_id' => $this->product->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->deleteJson(uri: "http://panda-estampados.com/api/v1/account/wishlists/{$this->wishlist->id}/items/{$item->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing(table: 'wishlist_items', data: ['id' => $item->id]);
    }

    #[TestAttr]
    public function remove_item_cross_wishlist_returns_404(): void
    {
        $otherWishlist = Wishlist::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);
        $item = WishlistItem::factory()->create([
            'wishlist_id' => $otherWishlist->id,
            'product_id' => $this->product->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->deleteJson(uri: "http://panda-estampados.com/api/v1/account/wishlists/{$this->wishlist->id}/items/{$item->id}")
            ->assertStatus(status: 404);
    }

    #[TestAttr]
    public function cross_customer_wishlist_returns_404(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherWishlist = Wishlist::factory()->create([
            'customer_id' => $otherCustomer->id,
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson(uri: "http://panda-estampados.com/api/v1/account/wishlists/{$otherWishlist->id}")
            ->assertStatus(status: 404);
    }
}
