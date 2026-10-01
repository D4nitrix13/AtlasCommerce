<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Marketing;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Marketing\Coupon;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del controller admin `Api\Admin\Marketing\CouponAdminController`.
 *
 * Cubre Resource CRUD + `apply` (test de aplicabilidad) + tenant isolation.
 */
final class CouponAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Store $store;

    private string $host = 'admin-coupons.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $role = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
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
            'email' => 'coupons@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => 'secret',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($this->store->id);
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/marketing/coupons");

        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_returns_coupons_with_shape(): void
    {
        Coupon::factory()->create(['code' => 'PROMO10', 'value' => 10]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/marketing/coupons");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'code', 'discount_type', 'value', 'is_active'],
            ],
        ]);
    }

    #[TestAttr]
    public function store_creates_coupon_and_returns_201(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/admin/marketing/coupons",
            data: [
                'code' => 'verano20',
                'discount_type' => 'percentage',
                'value' => 20,
                'minimum_amount' => 0,
                'is_active' => true,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.code', expect: 'VERANO20');
        $this->assertDatabaseHas(table: 'coupons', data: ['code' => 'VERANO20']);
    }

    #[TestAttr]
    public function store_validates_required_fields(): void
    {
        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/admin/marketing/coupons",
            data: [],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(['code', 'discount_type', 'value']);
    }

    #[TestAttr]
    public function apply_returns_discount_cents_for_percentage_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10)->create(['minimum_amount' => 0]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/admin/marketing/coupons/{$coupon->id}/apply",
            data: [
                'amount_cents' => 10000,
                'currency_code' => 'NIO',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'applies', expect: true);
        $response->assertJsonPath(path: 'discount_cents', expect: 1000);
    }

    #[TestAttr]
    public function apply_rejects_expired_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(1)->toDateString(),
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/admin/marketing/coupons/{$coupon->id}/apply",
            data: [
                'amount_cents' => 10000,
                'currency_code' => 'NIO',
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'applies', expect: false);
    }

    #[TestAttr]
    public function destroy_soft_deletes_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->deleteJson(uri: "http://{$this->host}/api/v1/admin/marketing/coupons/{$coupon->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($coupon);
    }
}
