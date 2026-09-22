<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Permission as PermissionModel;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de Roles (RF-AUTH-008).
 *
 * Cubre:
 *  - Listado paginado con filtros (q, is_active, with_trashed).
 *  - Show.
 *  - Crear rol con permisos iniciales.
 *  - Update (nombre, descripción, is_active, slug único).
 *  - Sync atómico de permisos.
 *  - Delete (soft) + protección del rol `admin`.
 *  - Listado de permisos del catálogo.
 *  - Authorization: 401 sin auth, 403 sin `auth.roles.manage`.
 */
#[Group('admin')]
#[Group('rbac')]
final class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private Role $adminRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Sembrar catálogo de permisos (roles admin + staff + permisos base).
        $this->seed(class: PermissionsCatalogSeeder::class);

        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        // Crear tienda + dominio para resolver `store.domain`.
        $currency = Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create([
            'slug' => 'panda-estampados',
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        // User con rol admin (bypasea todos los chequeos).
        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$store->id]);

        // User con rol staff (NO bypasea).
        $this->nonAdmin = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
        $this->nonAdmin->stores()->syncWithoutDetaching(ids: [$store->id]);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/roles');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_role_permission(): void
    {
        Sanctum::actingAs(user: $this->nonAdmin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/roles');

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_list_for_admin(): void
    {
        // Crear 2 roles extra para que el listado tenga varios items.
        Role::query()->create(attributes: [
            'name' => 'Vendedor',
            'slug' => 'vendedor',
            'is_active' => true,
        ]);
        Role::query()->create(attributes: [
            'name' => 'Auditor',
            'slug' => 'auditor',
            'is_active' => false,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/roles');

        $response->assertOk();
        // Cursor pagination response shape (RF-PERF-022): sin
        // current_page/last_page, tiene next_cursor/prev_cursor/has_more.
        $response->assertJsonStructure(structure: [
            'data' => [
                '*' => ['id', 'name', 'slug', 'is_active', 'is_protected'],
            ],
            'meta' => ['per_page', 'has_more', 'next_cursor', 'prev_cursor'],
        ]);
        $this->assertGreaterThanOrEqual(2, count($response->json(key: 'data')));
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        Role::query()->create(attributes: ['name' => 'Vendedor Mostrador', 'slug' => 'vendedor-mostrador', 'is_active' => true]);
        Role::query()->create(attributes: ['name' => 'Almacén', 'slug' => 'almacen', 'is_active' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/roles?q=vende');
        $response->assertOk();

        $slugs = collect($response->json(key: 'data'))->pluck('slug')->all();
        $this->assertContains(needle: 'vendedor-mostrador', haystack: $slugs);
        $this->assertNotContains(needle: 'almacen', haystack: $slugs);
    }

    #[Test]
    public function show_returns_role_with_permissions(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$this->staffRole->id);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.slug', expect: 'staff');
        $response->assertJsonStructure(structure: [
            'data' => ['id', 'name', 'slug', 'permissions'],
        ]);
        $this->assertIsArray($response->json(key: 'data.permissions'));
    }

    #[Test]
    public function store_creates_role_and_syncs_initial_permissions(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $permSlugs = PermissionModel::query()
            ->whereIn(column: 'slug', values: ['catalog.view', 'catalog.manage'])
            ->pluck('slug')
            ->all();

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/roles',
            data: [
                'name' => 'Catalog Manager',
                'slug' => 'catalog-manager',
                'description' => 'Gestiona el catálogo.',
                'is_active' => true,
                'permissions' => $permSlugs,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'catalog-manager');

        $role = Role::query()->where(column: 'slug', operator: '=', value: 'catalog-manager')->firstOrFail();
        $this->assertCount(2, $role->permissions);
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/roles', data: [
            'name' => 'Bad Slug',
            'slug' => 'Invalid Slug With Spaces',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/roles', data: [
            'name' => 'Otro Admin',
            'slug' => 'admin',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_unknown_permission_slug(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/roles', data: [
            'name' => 'Custom Role',
            'slug' => 'custom-role',
            'permissions' => ['catalog.view', 'non.existent.permission'],
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['permissions.1']);
    }

    #[Test]
    public function update_modifies_role_name_and_active_state(): void
    {
        $role = Role::query()->create(attributes: [
            'name' => 'Original',
            'slug' => 'original',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$role->id, data: [
            'name' => 'Renombrado',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.is_active', expect: false);

        $role->refresh();
        $this->assertSame(expected: 'Renombrado', actual: $role->name);
        $this->assertFalse($role->is_active);
        $this->assertSame(expected: 'original', actual: $role->slug, message: 'El slug no debe cambiar si no se envía.');
    }

    #[Test]
    public function update_prevents_renaming_the_admin_role(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$this->adminRole->id, data: [
            'slug' => 'super-admin',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function sync_permissions_replaces_set_atomically(): void
    {
        $role = Role::query()->create(attributes: [
            'name' => 'Custom',
            'slug' => 'custom-sync',
            'is_active' => true,
        ]);
        // Permisos iniciales.
        $role->permissions()->sync(ids: [
            PermissionModel::query()->where(column: 'slug', operator: '=', value: 'catalog.view')->firstOrFail()->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $newSlugs = ['catalog.manage', 'inventory.view'];
        $response = $this->putJson(
            uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$role->id.'/permissions',
            data: ['permissions' => $newSlugs],
        );

        $response->assertOk();

        $role->refresh();
        $slugs = $role->permissions->pluck('slug')->all();
        sort($slugs);
        $this->assertSame(expected: ['catalog.manage', 'inventory.view'], actual: $slugs);
        $this->assertNotContains(needle: 'catalog.view', haystack: $slugs, message: 'El sync debe reemplazar, no agregar.');
    }

    #[Test]
    public function sync_permissions_with_empty_array_clears_all(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$this->staffRole->id.'/permissions',
            data: ['permissions' => []],
        );

        $response->assertOk();
        $this->staffRole->refresh();
        $this->assertSame(expected: 0, actual: $this->staffRole->permissions()->count());
    }

    #[Test]
    public function sync_permissions_rejects_unknown_slug(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$this->staffRole->id.'/permissions',
            data: ['permissions' => ['catalog.view', 'fake.permission']],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['permissions.1']);
    }

    #[Test]
    public function destroy_soft_deletes_role(): void
    {
        $role = Role::query()->create(attributes: [
            'name' => 'Disposable',
            'slug' => 'disposable',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$role->id);
        $response->assertNoContent();

        $this->assertSoftDeleted(table: 'roles', data: ['id' => $role->id]);
    }

    #[Test]
    public function destroy_protects_admin_role_from_deletion(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$this->adminRole->id);

        $response->assertStatus(status: 500);
        $response->assertJsonFragment(['message' => 'El rol "admin" no puede eliminarse (es crítico para el bypass de permisos).']);

        $this->assertDatabaseHas(table: 'roles', data: ['id' => $this->adminRole->id]);
    }

    #[Test]
    public function destroy_nullifies_role_id_on_users_before_soft_delete(): void
    {
        $role = Role::query()->create(attributes: [
            'name' => 'Temp',
            'slug' => 'temp-role',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$role->id)
            ->assertNoContent();

        $this->assertNull($user->fresh()->role_id);
    }

    #[Test]
    public function list_permissions_returns_full_catalog(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/permissions');

        $response->assertOk();
        $response->assertJsonStructure(structure: [
            'data' => [
                '*' => ['id', 'name', 'slug', 'module', 'description'],
            ],
            'meta' => ['total', 'modules'],
        ]);

        $modules = $response->json(key: 'meta.modules');
        $this->assertIsArray($modules);
        $this->assertContains(needle: 'auth', haystack: $modules);
        $this->assertContains(needle: 'catalog', haystack: $modules);
    }

    #[Test]
    public function list_permissions_requires_auth_permissions_manage(): void
    {
        // Crear un user sin permisos especiales.
        $plain = User::factory()->create([
            'email' => 'plain@panda-estampados.com',
            'is_active' => true,
            'role_id' => null,
        ]);

        Sanctum::actingAs(user: $plain, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/permissions');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function update_changes_slug_when_not_olding_admin(): void
    {
        $role = Role::query()->create(attributes: [
            'name' => 'Original',
            'slug' => 'original-slug',
            'is_active' => true,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/roles/'.$role->id, data: [
            'slug' => 'renamed-slug',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.slug', expect: 'renamed-slug');
    }

    #[Test]
    public function admin_user_can_list_permissions_via_bypass(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/permissions');
        $response->assertOk();
    }
}
