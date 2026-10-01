<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Storefront\Checkout;

use App\Models\Auth\User as UserModel;
use App\Models\Carts\Cart;
use App\Models\Carts\CartItem;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del checkout multi-step (F4).
 *
 * Cubre preview + place + confirm usando un carrito sintético y mock
 * de payment (no integración real con Stripe).
 */
final class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Customer $customer;

    private Cart $cart;

    private CustomerAddress $address;

    private PaymentMethod $paymentMethod;

    private Store $store;

    private string $host = 'checkout.panda-estampados.test';

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = UserModel::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'checkout@test.test',
            'email_verified_at' => now(),
            'password' => 'secret',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => null,
        ]);

        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $this->customer->stores()->attach($this->store->id);

        $this->cart = Cart::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
            'status' => 'active',
        ]);

        $product = Product::factory()->create([
            'currency_id' => $this->store->currency_id,
        ]);

        CartItem::query()->create([
            'cart_id' => $this->cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'custom_design_id' => null,
            'cart_line_key' => 'line-'.Str::random(8),
            'quantity' => 2,
            'unit_price_cents' => 5000,
            'subtotal_cents' => 10000,
        ]);

        $this->address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_primary' => true,
        ]);

        $this->paymentMethod = PaymentMethod::factory()->create([
            'code' => 'cash-on-delivery',
            'is_active' => true,
        ]);
    }

    #[TestAttr]
    public function preview_returns_totals_without_creating_order(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/preview",
            data: ['cart_id' => $this->cart->id],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.cart_id', expect: $this->cart->id);
        $response->assertJsonPath(path: 'data.subtotal_cents', expect: 10000);
        $response->assertJsonPath(path: 'data.items_count', expect: 1);

        $this->assertSame(expected: 0, actual: Order::query()->count());
    }

    #[TestAttr]
    public function place_creates_order_in_active_status(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/place",
            data: [
                'cart_id' => $this->cart->id,
                'shipping_address_id' => $this->address->id,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_intent_id' => 'pi_test_123',
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.status', expect: 'pending');
        $response->assertJsonPath(path: 'data.payment_intent_id', expect: 'pi_test_123');

        $this->assertDatabaseHas(table: 'orders', data: [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'subtotal_cents' => 10000,
        ]);
        $this->assertDatabaseHas(table: 'payments', data: [
            'reference' => 'pi_test_123',
            'status' => 'pending',
        ]);
    }

    #[TestAttr]
    public function place_rejects_cart_that_doesnt_belong_to_user(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherCart = Cart::factory()->create([
            'customer_id' => $otherCustomer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/place",
            data: [
                'cart_id' => $otherCart->id,
                'shipping_address_id' => $this->address->id,
                'payment_method_id' => $this->paymentMethod->id,
            ],
        );

        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function place_rejects_inactive_cart(): void
    {
        $this->cart->update(['status' => 'converted']);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/place",
            data: [
                'cart_id' => $this->cart->id,
                'shipping_address_id' => $this->address->id,
                'payment_method_id' => $this->paymentMethod->id,
            ],
        );

        $response->assertStatus(status: 422);
    }

    #[TestAttr]
    public function confirm_marks_payment_as_succeeded(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $placeResponse = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/place",
            data: [
                'cart_id' => $this->cart->id,
                'shipping_address_id' => $this->address->id,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_intent_id' => 'pi_test_456',
            ],
        );
        $orderId = (int) $placeResponse->json('data.order_id');

        $confirmResponse = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/confirm/{$orderId}",
            data: ['payment_intent_id' => 'pi_test_456', 'status' => 'succeeded'],
        );

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath(path: 'data.status', expect: 'succeeded');

        $this->assertDatabaseHas(table: 'payments', data: [
            'reference' => 'pi_test_456',
            'status' => 'succeeded',
        ]);
    }

    #[TestAttr]
    public function confirm_returns_404_for_unknown_payment_intent(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $placeResponse = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/place",
            data: [
                'cart_id' => $this->cart->id,
                'shipping_address_id' => $this->address->id,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_intent_id' => 'pi_real_999',
            ],
        );
        $orderId = (int) $placeResponse->json('data.order_id');

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/confirm/{$orderId}",
            data: ['payment_intent_id' => 'pi_fake_000', 'status' => 'succeeded'],
        );

        $response->assertStatus(status: 404);
    }

    #[TestAttr]
    public function checkout_endpoints_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/checkout/preview",
            data: ['cart_id' => $this->cart->id],
        );

        $response->assertStatus(status: 401);
    }
}
