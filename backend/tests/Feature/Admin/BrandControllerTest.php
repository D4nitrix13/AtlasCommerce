<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de marcas (RF-CAT-024).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado + filtros (q, is_active, with_trashed).
 *  - Store + update con validación de unicidad.
 *  - Destroy soft (no falla con productos — nullOnDelete en FK).
 */
#[Group('admin')]
#[Group('catalog')]
final class BrandControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Role $adminRole;

    private Role $staffRole;

    private Category $category;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->category = Category::factory()->create();
        $this->currency = Currency::factory()->primary()->create();

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

    private function makeProduct(Brand $brand, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $brand->id,
            'currency_id' => $this->currency->id,
            'is_active' => true,
            'published_at' => now(),
        ], $attrs));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/brands');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/brands');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_list(): void
    {
        Brand::factory()->count(3)->create();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/brands');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 3);
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        Brand::factory()->create(['name' => 'Nike Sport', 'slug' => 'nike-sport']);
        Brand::factory()->create(['name' => 'Adidas', 'slug' => 'adidas']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/brands?q=nike');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function index_filters_by_is_active(): void
    {
        Brand::factory()->create(['is_active' => true]);
        Brand::factory()->create(['is_active' => false]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/brands?is_active=1');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function store_creates_brand(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/brands', data: [
            'name' => 'Nike',
            'slug' => 'nike',
            'description' => 'Ropa deportiva',
            'logo_url' => 'https://cdn.example.com/nike.png',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'nike');
        $response->assertJsonPath(path: 'data.name', expect: 'Nike');
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/brands', data: [
            'name' => 'Test',
            'slug' => 'Invalid Slug With Spaces',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_name(): void
    {
        Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/brands', data: [
            'name' => 'Nike',
            'slug' => 'nike-different',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['name']);
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        Brand::factory()->create(['name' => 'A', 'slug' => 'a-same']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/brands', data: [
            'name' => 'B',
            'slug' => 'a-same',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function update_modifies_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/brands/'.$brand->id, data: [
            'name' => 'Renombrada',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrada');
    }

    #[Test]
    public function destroy_soft_deletes_even_when_products_exist(): void
    {
        // Las marcas pueden borrarse aunque tengan productos. Soft delete
        // mantiene la fila del brand; el FK products.brand_id es nullOnDelete
        // solo dispara con HARD delete. Los productos siguen con su
        // brand_id original hasta que se haga un cleanup explícito.
        $brand = Brand::factory()->create();
        $product = $this->makeProduct(brand: $brand);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/brands/'.$brand->id);

        $response->assertNoContent();
        $this->assertNull(Brand::query()->find($brand->id));

        // El producto sigue existiendo, brand_id queda intacto.
        $product->refresh();
        $this->assertNotNull($product->fresh());
    }

    #[Test]
    public function index_includes_soft_deleted_with_flag(): void
    {
        $brand = Brand::factory()->create();
        $brand->delete();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/brands?with_trashed=1');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }
}
