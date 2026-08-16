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
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Sales\CheckoutService;
use App\Services\Shipping\ShipmentService;
use Core\Tenant\Application\StoreContextService;
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
        $this->storeA = Store::factory()->for($this->currency, 'currency')->create(attributes: ['name' => 'Tienda A']);
        $this->storeB = Store::factory()->for($this->currency, 'currency')->create(attributes: ['name' => 'Tienda B']);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->user->id]);
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
            ->create(attributes: ['base_price_cents' => 10_00]);
        $cartA->items()->create(attributes: [
            'product_id' => $product->id,
            'cart_line_key' => 'a',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'subtotal_cents' => 1000,
        ]);

        // Cambiar contexto a Tienda B y crear un carrito distinto.
        app(StoreContextService::class)->setStore($this->storeB);
        $cartB = Cart::factory()->for($this->customer)->for($this->storeB)->for($this->currency)->create();
        $cartB->items()->create(attributes: [
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
            ->create(attributes: ['base_price_cents' => 10_00]);

        // Cart en Tienda B con un item.
        $cartInB = Cart::factory()->for($this->customer)->for($this->storeB)->for($this->currency)->create();
        $cartInB->items()->create(attributes: [
            'product_id' => $product->id,
            'cart_line_key' => 'b',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'subtotal_cents' => 1000,
        ]);

        $address = CustomerAddress::factory()->for($this->customer)->create();

        // Context A → 404 (cart invisible).
        app(StoreContextService::class)->setStore($this->storeA);
        $this->postJson(uri: '/api/orders', data: [
            'cart_id' => $cartInB->id,
            'shipping_address_id' => $address->id,
            'payment_method_id' => $this->paymentMethod->id,
        ])->assertNotFound();

        // Context B → 201.
        app(StoreContextService::class)->setStore($this->storeB);
        $this->postJson(uri: '/api/orders', data: [
            'cart_id' => $cartInB->id,
            'shipping_address_id' => $address->id,
            'payment_method_id' => $this->paymentMethod->id,
        ])->assertCreated();
    }

    #[Test]
    public function shipping_zone_global_scope_filters_by_active_store(): void
    {
        // Zona explícita de cada tienda — ambas activas.
        $zoneA = $this->makeZone($this->storeA, ['name' => 'Zona A', 'base_cost_cents' => 5_00]);
        $zoneB = $this->makeZone($this->storeB, ['name' => 'Zona B', 'base_cost_cents' => 12_00]);

        // Context A: solo ve zona de A.
        app(StoreContextService::class)->setStore($this->storeA);
        $this->assertSame(1, ShippingZone::query()->count());
        $this->assertSame($zoneA->id, ShippingZone::query()->first()->id);

        // Context B: solo ve zona de B.
        app(StoreContextService::class)->setStore($this->storeB);
        $this->assertSame(1, ShippingZone::query()->count());
        $this->assertSame($zoneB->id, ShippingZone::query()->first()->id);
    }

    #[Test]
    public function shipping_checkout_uses_current_store_zone(): void
    {
        Sanctum::actingAs($this->user);

        // Zona con costo distintivo por tienda.
        $this->makeZone($this->storeA, ['name' => 'Zona A', 'base_cost_cents' => 5_00]);
        $this->makeZone($this->storeB, ['name' => 'Zona B', 'base_cost_cents' => 12_00]);

        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 10_00]);

        $address = CustomerAddress::factory()->for($this->customer)->create();

        // Checkout en Tienda A: shipping = 5.00.
        app(StoreContextService::class)->setStore($this->storeA);
        $cartA = $this->makeCartWithItem($this->storeA, $product, totalCents: 10_00);
        $orderA = app(CheckoutService::class)->checkout(
            cart: $cartA,
            shippingAddress: $address,
            paymentMethod: $this->paymentMethod,
        );
        $this->assertSame(5_00, $orderA->shipping_cents);

        // Checkout en Tienda B: shipping = 12.00.
        app(StoreContextService::class)->setStore($this->storeB);
        $cartB = $this->makeCartWithItem($this->storeB, $product, totalCents: 10_00);
        $orderB = app(CheckoutService::class)->checkout(
            cart: $cartB,
            shippingAddress: $address,
            paymentMethod: $this->paymentMethod,
        );
        $this->assertSame(12_00, $orderB->shipping_cents);
    }

    #[Test]
    public function shipment_rejects_cross_store_zone_in_invoice(): void
    {
        // Crear invoice con zona de OTRA tienda (forjada manualmente).
        $zoneInB = $this->makeZone($this->storeB, ['name' => 'Zona B', 'base_cost_cents' => 5_00]);

        $address = CustomerAddress::factory()->for($this->customer)->create();
        $order = Order::factory()
            ->for($this->customer)
            ->for($this->storeA)
            ->for($this->currency, 'currency')
            ->create();
        $order->update(['shipping_address_id' => $address->id]);

        $invoice = $order->invoices()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-CROSS-MT',
            'subtotal_cents' => 10_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'shipping_zone_id' => $zoneInB->id,
            'total_cents' => 15_00,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        assert($invoice instanceof Invoice);

        // ShipmentService debe lanzar CrossTenantShippingZoneException.
        $this->expectException(\App\Exceptions\Shipping\CrossTenantShippingZoneException::class);

        app(ShipmentService::class)->createForInvoice(invoice: $invoice);
    }

    /**
     * Helper para crear zonas asociadas a una tienda específica.
     *
     * @param  array<string, mixed>  $attrs  Overrides sobre el factory.
     */
    private function makeZone(Store $store, array $attrs = []): ShippingZone
    {
        return ShippingZone::factory()->forStore($store)->create(attributes: $attrs);
    }

    /**
     * Helper para crear un cart con un item de un total dado.
     */
    private function makeCartWithItem(Store $store, Product $product, int $totalCents): Cart
    {
        $cart = Cart::factory()
            ->for($this->customer)
            ->for($store)
            ->for($this->currency)
            ->create();
        $cart->items()->create(attributes: [
            'product_id' => $product->id,
            'cart_line_key' => 'k-'.$store->slug,
            'quantity' => 1,
            'unit_price_cents' => $totalCents,
            'subtotal_cents' => $totalCents,
        ]);

        return $cart;
    }
}
