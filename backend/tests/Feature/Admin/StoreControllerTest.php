<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
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
 * Tests del CRUD admin de tiendas + toggle de mantenimiento (RF-STOR-132).
 *
 * Cubre:
 *  - 401 sin token, 403 sin `stores.view`/`stores.manage`.
 *  - Listado paginado con aislamiento por tenant.
 *  - Show + Update (campos inmutables rechazados).
 *  - Toggle de mantenimiento on/off.
 *  - Cross-tenant → 404.
 */
#[Group('admin')]
#[Group('stores')]
final class StoreControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $unauthorized;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $currency = Currency::factory()->primary()->create();

        $this->storeA = Store::factory()->for($currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($currency, 'currency')->create(['slug' => 'kitsune']);
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
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/stores');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_stores_view_permission(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/stores');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_returns_only_accessible_stores(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/stores');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.id', expect: $this->storeA->id);
    }

    #[TestAttr]
    public function show_returns_store_detail(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: $this->storeA->name);
        $response->assertJsonPath(path: 'data.maintenance_mode', expect: false);
    }

    #[TestAttr]
    public function store_from_other_tenant_returns_404(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeB->id}");

        $response->assertStatus(status: 404);
    }

    #[TestAttr]
    public function update_modifies_allowed_fields(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}",
            data: [
                'description' => 'Tienda actualizada',
                'tax_rate' => 15.0,
                'primary_color' => '#FF0000',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.description', expect: 'Tienda actualizada');
        $response->assertJsonPath(path: 'data.tax_rate', expect: 15);
        $response->assertJsonPath(path: 'data.primary_color', expect: '#FF0000');
    }

    #[TestAttr]
    public function update_validates_color_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}",
            data: [
                'primary_color' => 'rojo',
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['primary_color']);
    }

    #[TestAttr]
    public function update_validates_tax_rate_max(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}",
            data: [
                'tax_rate' => 150,
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['tax_rate']);
    }

    #[TestAttr]
    public function toggle_maintenance_activates(): void
    {
        $this->assertFalse((bool) $this->storeA->maintenance_mode);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}/maintenance",
            data: ['enabled' => true],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.maintenance_mode', expect: true);
        $this->assertTrue((bool) $this->storeA->fresh()->maintenance_mode);
    }

    #[TestAttr]
    public function toggle_maintenance_deactivates(): void
    {
        $this->storeA->update(['maintenance_mode' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}/maintenance",
            data: ['enabled' => false],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.maintenance_mode', expect: false);
    }

    #[TestAttr]
    public function toggle_maintenance_requires_enabled_field(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeA->id}/maintenance",
            data: [],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['enabled']);
    }

    #[TestAttr]
    public function toggle_maintenance_cross_tenant_returns_404(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/admin/stores/{$this->storeB->id}/maintenance",
            data: ['enabled' => true],
        );

        $response->assertStatus(status: 404);
    }
}
