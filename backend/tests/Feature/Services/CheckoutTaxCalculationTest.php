<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Carts\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Sales\CheckoutService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('tax')]
final class CheckoutTaxCalculationTest extends TestCase
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
    public function zero_tax_when_store_has_no_tax_rate(): void
    {
        $store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['tax_rate' => 0]);
        app(StoreContextService::class)->setStore($store);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
        );

        $this->assertSame(10_000, $order->subtotal_cents);
        $this->assertSame(0, $order->tax_cents);
        $this->assertSame(10_000, $order->total_cents);
    }

    #[Test]
    public function tax_is_calculated_against_subtotal_using_store_rate(): void
    {
        $store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['tax_rate' => 15.0]);
        app(StoreContextService::class)->setStore($store);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
        );

        // 15% de 10_000 = 1500.
        $this->assertSame(10_000, $order->subtotal_cents);
        $this->assertSame(1500, $order->tax_cents);
        $this->assertSame(11_500, $order->total_cents);
    }

    #[Test]
    public function tax_is_rounded_half_to_even_to_avoid_drift(): void
    {
        // Subtotal 1 cent con 15% → 0.15 → banker's round = 0.
        // Subtotal 3 cents con 15% → 0.45 → banker's round = 0.
        // Subtotal 5 cents con 15% → 0.75 → banker's round = 1 (HALF_EVEN).
        $store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['tax_rate' => 15.0]);
        app(StoreContextService::class)->setStore($store);

        $cart = $this->makeCartWithItems($store, totalCents: 1);
        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );
        $this->assertSame(0, $order->tax_cents);

        $cart2 = $this->makeCartWithItems($store, totalCents: 5);
        $order2 = app(CheckoutService::class)->checkout(
            cart: $cart2, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );
        $this->assertSame(1, $order2->tax_cents);
    }

    #[Test]
    public function invoice_mirrors_order_tax_amount(): void
    {
        $store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['tax_rate' => 13.0]);
        app(StoreContextService::class)->setStore($store);

        $cart = $this->makeCartWithItems($store, totalCents: 20_000);
        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();

        $this->assertSame($order->tax_cents, $invoice->tax_cents);
        $this->assertSame($order->total_cents, $invoice->total_cents);
        $this->assertSame(2600, $invoice->tax_cents); // 13% de 20000
    }

    #[Test]
    public function tax_snapshot_is_preserved_even_if_store_rate_changes(): void
    {
        $store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['tax_rate' => 10.0]);
        app(StoreContextService::class)->setStore($store);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);
        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        // El store cambia su tasa DESPUÉS del checkout.
        $store->update(['tax_rate' => 25.0]);

        $order->refresh();
        // La orden mantiene su tax_cents original (snapshot implícito).
        $this->assertSame(1000, $order->tax_cents);
        $this->assertSame(11_000, $order->total_cents);
    }

    private function makeCartWithItems(Store $store, int $totalCents): Cart
    {
        $cart = Cart::factory()
            ->for($this->customer)
            ->for($store)
            ->for($this->currency)
            ->create();

        // Una sola línea para mantener el test simple. Subtotal = $totalCents.
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
