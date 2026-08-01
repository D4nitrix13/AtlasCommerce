<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Carts\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\CouponUsage;
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

#[Group('coupon')]
final class CheckoutCouponCalculationTest extends TestCase
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
    public function no_coupon_means_no_discount(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod,
        );

        $this->assertSame(0, $order->discount_cents);
        $this->assertSame(10_000, $order->total_cents);
    }

    #[Test]
    public function percentage_coupon_deducts_from_subtotal(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $coupon = Coupon::factory()->percentage(10.0)->create();
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod, coupon: $coupon,
        );

        // 10% de 10000 = 1000.
        $this->assertSame(1_000, $order->discount_cents);
        $this->assertSame(9_000, $order->total_cents);
    }

    #[Test]
    public function fixed_coupon_deducts_exact_cents(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $coupon = Coupon::factory()->fixed(cents: 5_00)->create();
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod, coupon: $coupon,
        );

        $this->assertSame(5_00, $order->discount_cents);
        $this->assertSame(9_500, $order->total_cents);
    }

    #[Test]
    public function minimum_amount_blocks_discount_but_allows_checkout(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $coupon = Coupon::factory()->percentage(20.0)->create([
            'minimum_amount' => 50.0, // 5000 cents
        ]);
        $cart = $this->makeCartWithItems($store, totalCents: 30_00); // < 5000

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod, coupon: $coupon,
        );

        // No se aplica descuento porque subtotal < minimum.
        $this->assertSame(0, $order->discount_cents);
        $this->assertSame(30_00, $order->total_cents);
    }

    #[Test]
    public function expired_coupon_is_silently_ignored(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $coupon = Coupon::factory()->percentage(50.0)->create([
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(1)->toDateString(),
        ]);
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod, coupon: $coupon,
        );

        $this->assertSame(0, $order->discount_cents);
    }

    #[Test]
    public function coupon_use_count_increments_after_checkout(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $coupon = Coupon::factory()->percentage(10.0)->create();
        $this->assertSame(0, $coupon->used_count);

        $cart = $this->makeCartWithItems($store, totalCents: 10_000);
        app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod, coupon: $coupon,
        );

        $coupon->refresh();
        $this->assertSame(1, $coupon->used_count);
        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->count());
    }

    #[Test]
    public function exhausted_coupon_does_not_apply_discount(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);
        $coupon = Coupon::factory()->percentage(50.0)->create([
            'usage_limit' => 1,
            'used_count' => 1,
        ]);
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart, shippingAddress: $this->address, paymentMethod: $this->paymentMethod, coupon: $coupon,
        );

        $this->assertSame(0, $order->discount_cents);
    }

    #[Test]
    public function discount_combines_with_tax_and_shipping_in_total(): void
    {
        $store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(['tax_rate' => 10.0]);
        app(StoreContextService::class)->setStore($store);

        // Crear una zona de envío.
        $zone = ShippingZone::factory()
            ->create(['base_cost_cents' => 5_00, 'is_active' => true]);

        $coupon = Coupon::factory()->percentage(20.0)->create(); // 20%
        $cart = $this->makeCartWithItems($store, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
            shippingZone: $zone,
            coupon: $coupon,
        );

        // 10000 - 2000 (discount) + 1000 (tax 10% del subtotal) + 500 (shipping) = 9500.
        $this->assertSame(2_000, $order->discount_cents);
        $this->assertSame(1_000, $order->tax_cents);
        $this->assertSame(5_00, $order->shipping_cents);
        $this->assertSame(9_500, $order->total_cents);
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
