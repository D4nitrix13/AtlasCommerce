<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Marketing\Coupon;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD admin de cupones (RF-MKT-060).
 *
 * Cubre:
 *  - Auth (401 sin token, 403 sin `marketing.manage`).
 *  - Listado con cupones globales (store_id=null) + scoped (store_id=propia).
 *  - Aislamiento por tenant: cupones de otras tiendas NO aparecen.
 *  - Store normaliza code a UPPERCASE.
 *  - Update no permite cambiar code (inmutable).
 *  - Soft-delete (no destruye historial de CouponUsage).
 *  - Cross-tenant → 404.
 */
#[Group('admin')]
#[Group('marketing')]
final class CouponControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $unauthorized;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Role $staffRole;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->currency = Currency::factory()->primary()->create();

        $this->storeA = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'kitsune']);
        StoreDomain::query()->create([
            'store_id' => $this->storeB->id,
            'domain' => 'kitsune.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        $this->staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
        $this->staff->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        $this->unauthorized = User::factory()->create();
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/coupons');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_marketing_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/coupons');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_returns_global_and_tenant_scoped_coupons(): void
    {
        Coupon::factory()->create(['code' => 'GLOBAL10', 'store_id' => null]);
        Coupon::factory()->create(['code' => 'PANDA10', 'store_id' => $this->storeA->id]);
        Coupon::factory()->create(['code' => 'KITSUNE10', 'store_id' => $this->storeB->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/coupons');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 2);
        $codes = collect(value: $response->json('data'))->pluck('code')->all();
        $this->assertContains(needle: 'GLOBAL10', haystack: $codes);
        $this->assertContains(needle: 'PANDA10', haystack: $codes);
        $this->assertNotContains(needle: 'KITSUNE10', haystack: $codes);
    }

    #[TestAttr]
    public function store_creates_coupon_with_uppercase_code(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/coupons',
            data: [
                'code' => 'summer-2026', // lowercase, debe normalizarse
                'discount_type' => 'percentage',
                'value' => 15.0,
                'is_active' => true,
                'store_id' => $this->storeA->id,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.code', expect: 'SUMMER-2026');
    }

    #[TestAttr]
    public function store_rejects_duplicate_code(): void
    {
        Coupon::factory()->create(['code' => 'DUPLICADO']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/coupons',
            data: [
                'code' => 'DUPLICADO',
                'discount_type' => 'percentage',
                'value' => 10.0,
                'store_id' => $this->storeA->id,
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['code']);
    }

    #[TestAttr]
    public function store_rejects_store_outside_tenant_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/coupons',
            data: [
                'code' => 'OTROTIENDA',
                'discount_type' => 'percentage',
                'value' => 10.0,
                'store_id' => $this->storeB->id,
            ],
        );

        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function update_does_not_change_code(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create(['store_id' => $this->storeA->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/v1/admin/coupons/{$coupon->id}",
            data: [
                'value' => 20.0,
                'is_active' => false,
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.code', expect: $coupon->code);
        $response->assertJsonPath(path: 'data.value', expect: 20);
        $response->assertJsonPath(path: 'data.is_active', expect: false);
    }

    #[TestAttr]
    public function destroy_soft_deletes_coupon(): void
    {
        $coupon = Coupon::factory()->percentage(10.0)->create(['store_id' => $this->storeA->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $this->deleteJson(uri: "http://panda-estampados.com/api/v1/admin/coupons/{$coupon->id}")
            ->assertNoContent();

        $this->assertSoftDeleted(table: 'coupons', data: ['id' => $coupon->id]);
    }

    #[TestAttr]
    public function coupon_from_other_tenant_returns_404(): void
    {
        $otherCoupon = Coupon::factory()->create(['store_id' => $this->storeB->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/coupons/{$otherCoupon->id}");

        $response->assertStatus(status: 404);
    }

    #[TestAttr]
    public function global_coupon_is_visible_to_all_tenants(): void
    {
        $globalCoupon = Coupon::factory()->create(['code' => 'GLOBAL15', 'store_id' => null]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/coupons/{$globalCoupon->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.code', expect: 'GLOBAL15');
    }
}
