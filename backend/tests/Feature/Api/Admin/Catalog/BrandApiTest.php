<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Catalog;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de marcas en /api/v1/admin/catalog/brands (RF-CAT-024).
 *
 * Las marcas son globales (sin tenant scope en el index, pero `products.brand_id`
 * filtra por tenant via `product_stores`). `products.brand_id` es nullOnDelete,
 * así que el destroy NO falla aunque haya productos asociados.
 *
 * Cubre:
 *  - Auth + permisos.
 *  - Index + filtros (q, is_active).
 *  - Show / Store / Update / Destroy.
 *  - Validación (slug único, kebab-case).
 *  - Soft delete + with_trashed.
 */
#[Group('admin')]
#[Group('catalog')]
#[Group('catalog-namespace')]
final class BrandApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Store $store;

    private Role $adminRole;

    private Role $staffRole;

    private string $host = 'panda-estampados.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->store = Store::factory()->create();
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        $this->staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands");

        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands");

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_list(): void
    {
        Brand::factory()->count(3)->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        Brand::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
        Brand::factory()->create(['name' => 'Globex', 'slug' => 'globex']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands?q=acme");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.slug', expect: 'acme-corp');
    }

    #[Test]
    public function show_returns_brand(): void
    {
        $brand = Brand::factory()->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands/{$brand->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.id', expect: $brand->id);
    }

    #[Test]
    public function show_returns_404_for_unknown_brand(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands/99999");

        $response->assertNotFound();
    }

    #[Test]
    public function store_creates_brand(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands", data: [
            'name' => 'NewBrand',
            'slug' => 'new-brand',
            'description' => 'Una marca nueva',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'new-brand');
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands", data: [
            'name' => 'Test',
            'slug' => 'Invalid Slug',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        Brand::factory()->create(['slug' => 'duplicate-slug']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands", data: [
            'name' => 'Otra',
            'slug' => 'duplicate-slug',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function update_modifies_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands/{$brand->id}", data: [
            'name' => 'Renombrada',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrada');
    }

    #[Test]
    public function destroy_soft_deletes_brand(): void
    {
        $brand = Brand::factory()->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands/{$brand->id}");

        $response->assertNoContent();
        $this->assertNull(Brand::query()->find($brand->id));
    }

    #[Test]
    public function index_includes_with_trashed_when_requested(): void
    {
        $active = Brand::factory()->create(['name' => 'Activa']);
        $deleted = Brand::factory()->create(['name' => 'Borrada']);
        $deleted->delete();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/brands?with_trashed=1");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }
}
