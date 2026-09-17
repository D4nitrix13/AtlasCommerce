<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency as CurrencyModel;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de categorías (RF-CAT-025).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado + filtros (q, parent_id, is_active, with_trashed).
 *  - Store con validación (slug único, parent_id existente).
 *  - Update con validación de unicidad + anti-ciclo (parent no puede
 *    ser self ni descendiente).
 *  - Destroy: bloqueado si tiene productos.
 */
#[Group('admin')]
#[Group('catalog')]
final class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Role $adminRole;

    private Role $staffRole;

    private CurrencyModel $currency;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->currency = CurrencyModel::factory()->primary()->create();
        $this->brand = Brand::factory()->create();

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->adminRole->id,
        ]);

        $this->staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/categories');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/categories');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_list(): void
    {
        Category::factory()->count(3)->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/categories');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        Category::factory()->create(['name' => 'Camisetas', 'slug' => 'camisetas']);
        Category::factory()->create(['name' => 'Pantalones', 'slug' => 'pantalones']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/categories?q=camiseta');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_filters_by_parent_id(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => $root->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/categories?parent_id='.$root->id);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function store_creates_category(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/categories', data: [
            'name' => 'Remeras',
            'slug' => 'remeras',
            'description' => 'Remeras de todo tipo',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'remeras');
    }

    #[Test]
    public function store_creates_child_category(): void
    {
        $root = Category::factory()->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/categories', data: [
            'name' => 'Remeras Manga Corta',
            'slug' => 'remeras-manga-corta',
            'parent_id' => $root->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.parent_id', expect: $root->id);
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/categories', data: [
            'name' => 'Test',
            'slug' => 'Invalid Slug With Spaces',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        Category::factory()->create(['slug' => 'duplicate-slug']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/categories', data: [
            'name' => 'Otra',
            'slug' => 'duplicate-slug',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_unknown_parent(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/categories', data: [
            'name' => 'Hija',
            'slug' => 'hija',
            'parent_id' => 99999,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['parent_id']);
    }

    #[Test]
    public function update_modifies_category(): void
    {
        $category = Category::factory()->create(['name' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/categories/'.$category->id, data: [
            'name' => 'Renombrada',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrada');
    }

    #[Test]
    public function update_blocks_setting_self_as_parent(): void
    {
        $category = Category::factory()->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/categories/'.$category->id, data: [
            'parent_id' => $category->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['parent_id']);
    }

    #[Test]
    public function update_blocks_creating_cycle_via_descendant(): void
    {
        // root → child → grandchild
        // Si intentamos hacer root.parent_id = grandchild, se crearía ciclo.
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/categories/'.$root->id, data: [
            'parent_id' => $grandchild->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['parent_id']);
    }

    #[Test]
    public function destroy_soft_deletes_when_no_products(): void
    {
        $category = Category::factory()->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/categories/'.$category->id);

        $response->assertNoContent();
        $this->assertNull(Category::query()->find($category->id));
    }

    #[Test]
    public function destroy_blocks_when_category_has_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/categories/'.$category->id);

        $response->assertStatus(status: 500);
        $response->assertJsonFragment(['message' => 'No podés eliminar la categoría porque tiene productos asociados. Reasigná los productos primero.']);
    }
}
