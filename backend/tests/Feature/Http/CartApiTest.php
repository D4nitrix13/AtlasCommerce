<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Auth\User;
use App\Models\Carts\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests HTTP del flujo de carrito: agregar items, leer, actualizar,
 * eliminar. Verifica autenticación Sanctum, multi-tenancy via
 * `BelongsToStore` global scope, y formato de respuesta del
 * `CartResource`.
 */
#[Group('http')]
final class CartApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[Test]
    public function guest_cannot_access_cart_endpoints(): void
    {
        $this->getJson('/api/carts')->assertUnauthorized();
        $this->postJson('/api/carts')->assertUnauthorized();
    }

    #[Test]
    public function store_creates_or_returns_existing_active_cart(): void
    {
        Sanctum::actingAs($this->user);

        // Primer store → crea cart (201 Created).
        $response = $this->postJson('/api/carts');
        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.store_id', $this->store->id);
        $response->assertJsonPath('data.currency_id', $this->currency->id);
        $cartId = $response->json('data.id');

        $this->assertDatabaseHas('carts', [
            'id' => $cartId,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'active',
        ]);

        // Segundo store (mismo contexto) → idempotente, devuelve el mismo.
        $this->postJson('/api/carts')
            ->assertOk()
            ->assertJsonPath('data.id', $cartId);
    }

    #[Test]
    public function can_add_item_to_cart_and_subtotal_updates(): void
    {
        Sanctum::actingAs($this->user);
        $cart = Cart::factory()->for($this->customer)->for($this->store)->for($this->currency)->create();

        $category = Category::factory()->create();
        $product = Product::factory()
            ->for(factory: $category, relationship: 'category')
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['base_price_cents' => 25_00]);

        // Agregar 2 unidades.
        $response = $this->postJson("/api/carts/{$cart->id}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.quantity', 2);
        $response->assertJsonPath('data.unit_price.cents', 2500);
        $response->assertJsonPath('data.subtotal.cents', 5000);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'subtotal_cents' => 5000,
        ]);

        // Subtotal del cart se actualiza.
        $this->getJson("/api/carts/{$cart->id}")
            ->assertJsonPath('data.subtotal_cents', 5000)
            ->assertJsonPath('data.items_count', 1);
    }

    #[Test]
    public function adding_same_product_twice_sums_quantity_not_duplicates(): void
    {
        Sanctum::actingAs($this->user);
        $cart = Cart::factory()->for($this->customer)->for($this->store)->for($this->currency)->create();
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(['base_price_cents' => 10_00]);

        $this->postJson("/api/carts/{$cart->id}/items", [
            'product_id' => $product->id, 'quantity' => 1,
        ])->assertCreated();

        // Segunda vez: 200 OK porque actualiza cantidad (no crea item).
        $this->postJson("/api/carts/{$cart->id}/items", [
            'product_id' => $product->id, 'quantity' => 3,
        ])->assertOk();

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'subtotal_cents' => 40_00,
        ]);
    }

    #[Test]
    public function variant_price_overrides_product_price(): void
    {
        Sanctum::actingAs($this->user);
        $cart = Cart::factory()->for($this->customer)->for($this->store)->for($this->currency)->create();
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(['base_price_cents' => 10_00]);

        $variant = ProductVariant::factory()
            ->for($product)
            ->create(['price_cents' => 12_50]);

        $response = $this->postJson("/api/carts/{$cart->id}/items", [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.unit_price.cents', 1250);
    }

    #[Test]
    public function cannot_modify_items_on_converted_cart(): void
    {
        Sanctum::actingAs($this->user);
        $cart = Cart::factory()
            ->for($this->customer)
            ->for($this->store)
            ->for($this->currency)
            ->create(['status' => 'converted']);
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create();

        $this->postJson("/api/carts/{$cart->id}/items", [
            'product_id' => $product->id, 'quantity' => 1,
        ])->assertStatus(422);
    }

    #[Test]
    public function cannot_access_another_customers_cart(): void
    {
        Sanctum::actingAs($this->user);
        $otherCustomer = Customer::factory()->create();
        $cart = Cart::factory()
            ->for($otherCustomer)
            ->for($this->store)
            ->for($this->currency)
            ->create();

        $this->getJson("/api/carts/{$cart->id}")->assertForbidden();
    }

    #[Test]
    public function cart_show_returns_404_for_wrong_store(): void
    {
        Sanctum::actingAs($this->user);

        // Crear cart en OTRA tienda (diferente del contexto actual).
        $otherStore = Store::factory()->for($this->currency, 'currency')->create();
        $cartInOtherStore = Cart::factory()
            ->for($this->customer)
            ->for($otherStore)
            ->for($this->currency)
            ->create();

        // El global scope BelongsToStore filtra este cart porque su store_id
        // no coincide con el contexto actual.
        $this->getJson("/api/carts/{$cartInOtherStore->id}")
            ->assertNotFound();
    }
}
