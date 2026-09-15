<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Auth\User;
use App\Models\Carts\Cart;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Marketing\Coupon;
use App\Models\Payments\PaymentMethod;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Core\Tenant\Application\StoreContextService;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del endpoint público `POST /api/coupons/validate` (RF-MKT-060).
 *
 * Cubre:
 *  - 401 sin token (la ruta requiere autenticación porque opera sobre
 *    un carrito del cliente).
 *  - 422: cupón no existe, inactivo, expirado, fuera de vigencia,
 *    agotado, cross-tenant, no cumple minimum_amount.
 *  - 200: cupón percentage aplicado correctamente.
 *  - 200: cupón fixed aplicado correctamente.
 *  - El cupón NO se aplica (used_count no incrementa) — sólo se valida.
 */
#[Group('marketing')]
#[Group('coupon')]
final class CouponValidateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Store $store;

    private Currency $currency;

    private Product $product;

    private Cart $cart;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'panda']);
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->user->id]);
        $this->paymentMethod = PaymentMethod::factory()->create();
        $this->product = Product::factory()->for($this->currency, 'currency')->create();

        $this->cart = Cart::factory()
            ->for($this->customer)
            ->for($this->store)
            ->for($this->currency)
            ->create();

        $this->cart->items()->create([
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'cart_line_key' => 'line-'.uniqid(),
            'quantity' => 4,
            'unit_price_cents' => 25_00,
            'subtotal_cents' => 100_00,
        ]);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[TestAttr]
    public function it_returns_401_without_authentication(): void
    {
        $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => 'PROMO10',
            'cart_id' => $this->cart->id,
        ])->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_validates_percentage_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create([
            'store_id' => $this->store->id,
            'usage_limit' => 100,
            'used_count' => 0,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.code', expect: $coupon->code);
        $response->assertJsonPath(path: 'data.discount_type', expect: 'percentage');
        // 100_00 * 10% = 10_00.
        $response->assertJsonPath(path: 'data.discount_cents', expect: 10_00);

        // El cupón NO se aplica — used_count sigue en 0.
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    #[TestAttr]
    public function it_validates_fixed_coupon(): void
    {
        $coupon = Coupon::factory()->fixed(cents: 15_00)->create([
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.discount_type', expect: 'fixed');
        $response->assertJsonPath(path: 'data.discount_cents', expect: 15_00);
    }

    #[TestAttr]
    public function it_normalizes_code_to_uppercase(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create(['store_id' => $this->store->id]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => strtolower($coupon->code),
            'cart_id' => $this->cart->id,
        ]);

        $response->assertOk();
    }

    #[TestAttr]
    public function it_returns_422_for_nonexistent_coupon(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => 'NOEXISTE',
            'cart_id' => $this->cart->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'El cupón no existe.');
    }

    #[TestAttr]
    public function it_returns_422_for_inactive_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create([
            'store_id' => $this->store->id,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'El cupón está inactivo.');
    }

    #[TestAttr]
    public function it_returns_422_for_expired_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create([
            'store_id' => $this->store->id,
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(1)->toDateString(),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'El cupón está expirado.');
    }

    #[TestAttr]
    public function it_returns_422_for_exhausted_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create([
            'store_id' => $this->store->id,
            'usage_limit' => 5,
            'used_count' => 5,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'El cupón agotó su límite de uso.');
    }

    #[TestAttr]
    public function it_returns_422_for_cross_tenant_coupon(): void
    {
        $otherStore = Store::factory()->for($this->currency, 'currency')->create();
        $coupon = Coupon::factory()->percentage(10.0)->create(['store_id' => $otherStore->id]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        // Cross-tenant: mismo mensaje que "no existe" — no leakeamos existencia.
        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: 'El cupón no existe.');
    }

    #[TestAttr]
    public function it_returns_200_with_zero_discount_when_minimum_amount_not_met(): void
    {
        $coupon = Coupon::factory()->percentage(50.0)->create([
            'store_id' => $this->store->id,
            'minimum_amount' => 500.00, // subtotal actual: 100_00 (=$100) < $500 → no aplica
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/coupons/validate', data: [
            'code' => $coupon->code,
            'cart_id' => $this->cart->id,
        ]);

        // El cupón es válido pero el carrito no alcanza el mínimo.
        // 200 OK con discount_cents = 0 (el frontend decide si mostrar
        // "válido pero no aplica al carrito actual").
        $response->assertOk();
        $response->assertJsonPath(path: 'data.discount_cents', expect: 0);
    }
}
