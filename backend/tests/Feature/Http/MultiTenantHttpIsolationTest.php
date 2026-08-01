<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Auth\User;
use App\Models\Carts\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\PaymentMethod;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Stores\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests HTTP de aislamiento multi-tenant a nivel de request.
 *
 * Verifica que el global scope `BelongsToStore` filtra correctamente
 * cuando la tienda activa cambia entre requests (por ejemplo, el
 * middleware `ResolveStoreFromDomain` resuelve una tienda por dominio).
 *
 * El mismo cliente puede tener carritos en distintas tiendas. Los
 * endpoints deben respetar el contexto actual, no el orden de creación.
 */
#[Group('http')]
final class MultiTenantHttpIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $storeA;

    private Store $storeB;

    private Customer $customer;

    private User $user;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->storeA = Store::factory()->for($this->currency, 'currency')->create(['name' => 'Tienda A']);
        $this->storeB = Store::factory()->for($this->currency, 'currency')->create(['name' => 'Tienda B']);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $this->paymentMethod = PaymentMethod::factory()->create();
    }

    #[Test]
    public function same_customer_can_have_separate_carts_per_store(): void
    {
        Sanctum::actingAs($this->user);

        // Crear carrito en Tienda A.
        app(StoreContextService::class)->setStore($this->storeA);
        $cartA = Cart::factory()->for($this->customer)->for($this->storeA)->for($this->currency)->create();
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(['base_price_cents' => 10_00]);
        $cartA->items()->create([
            'product_id' => $product->id,
            'cart_line_key' => 'a',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'subtotal_cents' => 1000,
        ]);

        // Cambiar contexto a Tienda B y crear un carrito distinto.
        app(StoreContextService::class)->setStore($this->storeB);
        $cartB = Cart::factory()->for($this->customer)->for($this->storeB)->for($this->currency)->create();
        $cartB->items()->create([
            'product_id' => $product->id,
            'cart_line_key' => 'b',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'subtotal_cents' => 1000,
        ]);

        // GET /api/carts en contexto A: solo ve cart A.
        app(StoreContextService::class)->setStore($this->storeA);
        $response = $this->getJson('/api/carts');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($cartA->id, $response->json('data.0.id'));
        $this->assertSame($this->storeA->id, $response->json('data.0.store_id'));

        // GET /api/carts en contexto B: solo ve cart B.
        app(StoreContextService::class)->setStore($this->storeB);
        $response = $this->getJson('/api/carts');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($cartB->id, $response->json('data.0.id'));
        $this->assertSame($this->storeB->id, $response->json('data.0.store_id'));
    }

    #[Test]
    public function adding_item_to_other_store_cart_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        // Cart pertenece a Tienda B.
        $cartInB = Cart::factory()->for($this->customer)->for($this->storeB)->for($this->currency)->create();

        // Contexto es Tienda A.
        app(StoreContextService::class)->setStore($this->storeA);

        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create();

        // El cart está oculto por el global scope, así que findOrFail lanza 404.
        $this->postJson("/api/carts/{$cartInB->id}/items", [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertNotFound();
    }

    #[Test]
    public function cannot_show_cart_from_other_store(): void
    {
        Sanctum::actingAs($this->user);

        $cartInB = Cart::factory()->for($this->customer)->for($this->storeB)->for($this->currency)->create();

        // Context A: cart de B invisible.
        app(StoreContextService::class)->setStore($this->storeA);
        $this->getJson("/api/carts/{$cartInB->id}")->assertNotFound();

        // Context B: visible.
        app(StoreContextService::class)->setStore($this->storeB);
        $this->getJson("/api/carts/{$cartInB->id}")->assertOk();
    }

    #[Test]
    public function checkout_only_allows_active_carts_from_current_store(): void
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(['base_price_cents' => 10_00]);

        // Cart en Tienda B con un item.
        $cartInB = Cart::factory()->for($this->customer)->for($this->storeB)->for($this->currency)->create();
        $cartInB->items()->create([
            'product_id' => $product->id,
            'cart_line_key' => 'b',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'subtotal_cents' => 1000,
        ]);

        $address = CustomerAddress::factory()->for($this->customer)->create();

        // Context A → 404 (cart invisible).
        app(StoreContextService::class)->setStore($this->storeA);
        $this->postJson('/api/orders', [
            'cart_id' => $cartInB->id,
            'shipping_address_id' => $address->id,
            'payment_method_id' => $this->paymentMethod->id,
        ])->assertNotFound();

        // Context B → 201.
        app(StoreContextService::class)->setStore($this->storeB);
        $this->postJson('/api/orders', [
            'cart_id' => $cartInB->id,
            'shipping_address_id' => $address->id,
            'payment_method_id' => $this->paymentMethod->id,
        ])->assertCreated();
    }
}
