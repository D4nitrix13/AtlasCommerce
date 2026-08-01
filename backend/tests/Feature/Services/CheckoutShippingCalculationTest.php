<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Carts\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\PaymentMethod;
use App\Models\Settings\Currency;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Sales\CheckoutService;
use App\Services\Stores\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('shipping')]
final class CheckoutShippingCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Customer $customer;

    private CustomerAddress $address;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->customer = Customer::factory()->create();
        $this->address = CustomerAddress::factory()->for($this->customer)->create();
        $this->paymentMethod = PaymentMethod::factory()->create();
    }

    #[Test]
    public function zero_shipping_when_no_zones_exist(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        $this->assertSame(0, $order->shipping_cents);
        $this->assertSame(10_000, $order->total_cents);
    }

    #[Test]
    public function default_zone_is_picked_when_no_zone_provided(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        ShippingZone::factory()->create([
            'name' => 'Managua',
            'base_cost_cents' => 5_00,
            'is_active' => true,
        ]);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        $this->assertSame(5_00, $order->shipping_cents);
        $this->assertSame(10_500, $order->total_cents);
    }

    #[Test]
    public function client_chosen_zone_overrides_default(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        ShippingZone::factory()->create(['name' => 'Managua', 'base_cost_cents' => 5_00, 'is_active' => true]);
        $express = ShippingZone::factory()->create(['name' => 'Express', 'base_cost_cents' => 12_00, 'is_active' => true]);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
            shippingZone: $express,
        );

        $this->assertSame(12_00, $order->shipping_cents);
    }

    #[Test]
    public function shipping_snapshot_is_preserved_even_if_zone_cost_changes(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        $zone = ShippingZone::factory()->create(['base_cost_cents' => 5_00, 'is_active' => true]);
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        $this->assertSame(5_00, $order->shipping_cents);

        // La zona cambia su costo después.
        $zone->update(['base_cost_cents' => 9_00]);
        $order->refresh();

        $this->assertSame(5_00, $order->shipping_cents);
    }

    #[Test]
    public function invoice_mirrors_order_shipping_amount(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        ShippingZone::factory()->create(['base_cost_cents' => 7_50, 'is_active' => true]);

        $cart = $this->makeCartWithItems($store, totalCents: 20_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        $invoice = $order->invoices()->first();

        $this->assertSame(7_50, $invoice->shipping_cents);
    }

    private function makeCartWithItems(Store $store, int $totalCents): Cart
    {
        $cart = Cart::factory()->for($this->customer)->for($store)->for($this->currency)->create();
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(['base_price_cents' => $totalCents]);

        $cart->items()->create([
            'product_id' => $product->id,
            'cart_line_key' => 'k',
            'quantity' => 1,
            'unit_price_cents' => $totalCents,
            'subtotal_cents' => $totalCents,
        ]);

        return $cart;
    }
}
