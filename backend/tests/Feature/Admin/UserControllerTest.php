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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de usuarios (RF-AUTH-009).
 *
 * Cubre:
 *  - Auth: 401 sin token, 403 sin `auth.users.manage`.
 *  - Listado paginado + filtros (q, role_id, store_id, is_active).
 *  - Aislamiento por tenant: no ver users de otras tiendas.
 *  - Show.
 *  - Crear (con role + stores).
 *  - Update (incluyendo sync de stores).
 *  - Suspend / Reactivate.
 *  - Delete con protecciones (no self, no último admin).
 *  - Auto-demotion bloqueada.
 *  - Asignar store fuera del scope del admin.
 */
#[Group('admin')]
#[Group('rbac')]
final class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

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

        // Dos tiendas en el mismo instance. La B es para tests cross-tenant.
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

        // Admin activo (bypasea permisos). Asociado a storeA.
        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        // Staff (sin bypass). Asociado a storeA.
        $this->staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
        $this->staff->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/users');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/users');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_users_in_tenant(): void
    {
        // 3 users en storeA (incluyendo el admin y el staff del setUp).
        User::factory()->count(2)->create([
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ])->each(function (User $u): void {
            $u->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);
        });

        // 1 user en storeB (NO debe aparecer).
        $outsider = User::factory()->create([
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ]);
        $outsider->stores()->syncWithoutDetaching(ids: [$this->storeB->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/users');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 4); // admin + staff + 2 nuevos
        $ids = collect($response->json(key: 'data'))->pluck('id')->all();
        $this->assertNotContains(needle: $outsider->id, haystack: $ids);
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        User::factory()->create([
            'email' => 'mario@panda-estampados.com',
            'first_name' => 'Mario',
            'last_name' => 'Bros',
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ])->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/users?q=mario');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function show_returns_user_with_role_and_stores(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id);

        $response->assertOk();
        $response->assertJsonStructure(structure: [
            'data' => ['id', 'email', 'full_name', 'role', 'store_ids', 'permissions'],
        ]);
    }

    #[Test]
    public function show_returns_404_for_user_outside_tenant(): void
    {
        $outsider = User::factory()->create([
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ]);
        $outsider->stores()->syncWithoutDetaching(ids: [$this->storeB->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/users/'.$outsider->id);
        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_creates_user_and_syncs_stores(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users', data: [
            'email' => 'nuevo@panda-estampados.com',
            'password' => 'secret-password-123',
            'first_name' => 'Nuevo',
            'last_name' => 'Empleado',
            'role_id' => $this->staffRole->id,
            'store_ids' => [$this->storeA->id],
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.email', expect: 'nuevo@panda-estampados.com');

        $user = User::query()->where(column: 'email', operator: '=', value: 'nuevo@panda-estampados.com')->firstOrFail();
        $this->assertTrue(Hash::check(value: 'secret-password-123', hashedValue: $user->password));
        $this->assertSame(expected: 1, actual: $user->stores()->count());
    }

    #[Test]
    public function store_rejects_duplicate_email(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users', data: [
            'email' => $this->staff->email,
            'password' => 'secret-password',
            'first_name' => 'Dup',
            'last_name' => 'User',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['email']);
    }

    #[Test]
    public function store_rejects_store_outside_admin_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users', data: [
            'email' => 'intruder@panda-estampados.com',
            'password' => 'secret-password',
            'first_name' => 'X',
            'last_name' => 'Y',
            'store_ids' => [$this->storeB->id], // storeB no es accesible
        ]);

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function store_rejects_unknown_role(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users', data: [
            'email' => 'newuser@panda-estampados.com',
            'password' => 'secret-password',
            'first_name' => 'X',
            'last_name' => 'Y',
            'role_id' => 99999,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['role_id']);
    }

    #[Test]
    public function update_changes_email_and_role(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id, data: [
            'email' => 'staff-renamed@panda-estampados.com',
            'role_id' => $this->adminRole->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.email', expect: 'staff-renamed@panda-estampados.com');
        $this->staff->refresh();
        $this->assertSame(expected: $this->adminRole->id, actual: $this->staff->role_id);
    }

    #[Test]
    public function update_syncs_stores_atomically(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id, data: [
            'store_ids' => [],
        ]);

        $response->assertOk();
        $this->staff->refresh();
        $this->assertSame(expected: 0, actual: $this->staff->stores()->count());
    }

    #[Test]
    public function update_blocks_self_role_change(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->admin->id, data: [
            'role_id' => $this->staffRole->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['role_id']);
    }

    #[Test]
    public function update_rejects_store_outside_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id, data: [
            'store_ids' => [$this->storeB->id],
        ]);

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function suspend_sets_is_active_false(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id.'/suspend');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_active', expect: false);
    }

    #[Test]
    public function reactivate_sets_is_active_true(): void
    {
        $this->staff->update(['is_active' => false]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id.'/reactivate');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_active', expect: true);
    }

    #[Test]
    public function suspend_blocks_self(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->admin->id.'/suspend');

        $response->assertStatus(status: 500);
        $response->assertJsonFragment(['message' => 'No podés suspenderte a vos mismo.']);
    }

    #[Test]
    public function destroy_deletes_user(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->staff->id);

        $response->assertNoContent();
        $this->assertNull(User::query()->find($this->staff->id));
    }

    #[Test]
    public function destroy_blocks_self(): void
    {
        // Crear OTRO admin para que el lockout no dispare primero.
        $secondAdmin = User::factory()->create([
            'email' => 'admin2@panda-estampados.com',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
        $secondAdmin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->admin->id);

        $response->assertStatus(status: 500);
        $response->assertJsonFragment(['message' => 'No podés eliminarte a vos mismo.']);
    }

    #[Test]
    public function destroy_blocks_last_admin_in_tenant(): void
    {
        // El admin actual es el ÚNICO admin del tenant storeA.
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        // Crear otro admin en storeB para que storeA siga teniendo
        // 1 solo admin. (storeB tiene su propio admin, no cuenta.)
        $otherTenantAdmin = User::factory()->create([
            'email' => 'admin@kitsune.com',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
        $otherTenantAdmin->stores()->syncWithoutDetaching(ids: [$this->storeB->id]);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/users/'.$this->admin->id);

        $response->assertStatus(status: 500);
        $response->assertJsonFragment(['message' => 'No podés borrar al ÚNICO admin del tenant.']);
    }

    #[Test]
    public function destroy_allows_when_other_admins_exist_in_tenant(): void
    {
        // Crear OTRO admin en storeA. Ahora hay 2 admins en el tenant.
        $secondAdmin = User::factory()->create([
            'email' => 'admin2@panda-estampados.com',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
        $secondAdmin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/users/'.$secondAdmin->id);

        $response->assertNoContent();
        $this->assertNull(User::query()->find($secondAdmin->id));
    }

    #[Test]
    public function destroy_allows_non_admin_user_regardless_of_count(): void
    {
        // Crear más staff. Borrar uno. No hay restricciones de lockout.
        $extra = User::factory()->create([
            'email' => 'extra@panda-estampados.com',
            'role_id' => $this->staffRole->id,
            'is_active' => true,
        ]);
        $extra->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/users/'.$extra->id);

        $response->assertNoContent();
    }
}
