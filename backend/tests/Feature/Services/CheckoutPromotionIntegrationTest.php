<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Carts\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\Promotion;
use App\Models\Payments\PaymentMethod;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Sales\CheckoutService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de integración: promociones automáticas en checkout (RF-MKT-061).
 *
 * Cubre que el `CheckoutService` acumule correctamente:
 *  - Solo promoción (sin cupón) → descuento de promo.
 *  - Solo cupón (sin promo) → descuento de cupón (regresión).
 *  - Cupón + promo → ambos descuentos se suman.
 *  - Descuento total nunca excede el subtotal.
 */
#[Group('checkout')]
#[Group('promotion')]
final class CheckoutPromotionIntegrationTest extends TestCase
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
    public function promotion_only_applies_discount(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 10_000]);

        Promotion::factory()
            ->percentage(20.0)
            ->forStore($store)
            ->withProduct($product)
            ->create();

        $cart = $this->makeCartWithItem($store, $product, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
        );

        // 20% de 10_000 = 2_000.
        $this->assertSame(2_000, $order->discount_cents);
        $this->assertSame(8_000, $order->total_cents);
    }

    #[Test]
    public function coupon_only_still_works(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        $coupon = Coupon::factory()->percentage(10.0)->create();
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 10_000]);
        $cart = $this->makeCartWithItem($store, $product, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
            coupon: $coupon,
        );

        $this->assertSame(1_000, $order->discount_cents);
        $this->assertSame(9_000, $order->total_cents);
    }

    #[Test]
    public function coupon_and_promotion_stack(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        $coupon = Coupon::factory()->percentage(10.0)->create();

        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 10_000]);

        Promotion::factory()
            ->percentage(20.0)
            ->forStore($store)
            ->withProduct($product)
            ->create();

        $cart = $this->makeCartWithItem($store, $product, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
            coupon: $coupon,
        );

        // Cupón 10% (1000) + promo 20% (2000) = 3000.
        $this->assertSame(3_000, $order->discount_cents);
        $this->assertSame(7_000, $order->total_cents);
    }

    #[Test]
    public function total_discount_is_capped_at_subtotal(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        // Cupón 50% + promo 80% → 130% sobre 10_000 = 13_000 → capped a 10_000.
        $coupon = Coupon::factory()->percentage(50.0)->create();
        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 10_000]);

        Promotion::factory()
            ->percentage(80.0)
            ->forStore($store)
            ->withProduct($product)
            ->create();

        $cart = $this->makeCartWithItem($store, $product, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
            coupon: $coupon,
        );

        $this->assertSame(10_000, $order->discount_cents);
        $this->assertSame(0, $order->total_cents);
    }

    #[Test]
    public function promotion_from_other_store_does_not_apply(): void
    {
        $store = Store::factory()->for($this->currency, 'currency')->create();
        $otherStore = Store::factory()->for($this->currency, 'currency')->create();
        app(StoreContextService::class)->setStore($store);

        $product = Product::factory()
            ->for(Category::factory(), 'category')
            ->for($this->currency, 'currency')
            ->create(attributes: ['base_price_cents' => 10_000]);

        Promotion::factory()
            ->percentage(50.0)
            ->forStore($otherStore)
            ->withProduct($product)
            ->create();

        $cart = $this->makeCartWithItem($store, $product, totalCents: 10_000);

        $order = app(CheckoutService::class)->checkout(
            cart: $cart,
            shippingAddress: $this->address,
            paymentMethod: $this->paymentMethod,
        );

        $this->assertSame(0, $order->discount_cents);
        $this->assertSame(10_000, $order->total_cents);
    }

    private function makeCartWithItem(Store $store, Product $product, int $totalCents): Cart
    {
        $cart = Cart::factory()->for($this->customer)->for($store)->for($this->currency)->create();
        $cart->items()->create(attributes: [
            'product_id' => $product->id,
            'cart_line_key' => 'k',
            'quantity' => 1,
            'unit_price_cents' => $totalCents,
            'subtotal_cents' => $totalCents,
        ]);

        return $cart;
    }
}
