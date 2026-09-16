<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttribute;
use App\Models\Settings\Currency;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de atributos (RF-CAT-027 parte 1).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado + filtros (q, type, is_filterable).
 *  - Store con validación (slug único).
 *  - Update con unicidad.
 *  - Destroy: bloqueado si está asignado a productos; OK si no.
 */
#[Group('admin')]
#[Group('catalog')]
final class AttributeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Role $adminRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

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

    /**
     * Helper: crea un atributo sin factory (no existe factory).
     */
    private function makeAttribute(array $overrides = []): ProductAttribute
    {
        $defaults = [
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
            'description' => null,
            'is_filterable' => true,
            'is_required' => false,
        ];

        return ProductAttribute::query()->create(array_merge($defaults, $overrides));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_list(): void
    {
        $this->makeAttribute(['name' => 'Color', 'slug' => 'color']);
        $this->makeAttribute(['name' => 'Talla', 'slug' => 'talla']);
        $this->makeAttribute(['name' => 'Material', 'slug' => 'material']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 3);
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        $this->makeAttribute(['name' => 'Color', 'slug' => 'color']);
        $this->makeAttribute(['name' => 'Talla', 'slug' => 'talla']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes?q=color');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function index_filters_by_type(): void
    {
        $this->makeAttribute(['name' => 'Color', 'slug' => 'color', 'type' => 'select']);
        $this->makeAttribute(['name' => 'Material', 'slug' => 'material', 'type' => 'text']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes?type=text');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function store_creates_attribute(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes', data: [
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
            'description' => 'Color del producto',
            'is_filterable' => true,
            'is_required' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'color');
        $response->assertJsonPath(path: 'data.is_required', expect: true);
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes', data: [
            'name' => 'Test',
            'slug' => 'Invalid Slug',
            'type' => 'text',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        $this->makeAttribute(['slug' => 'duplicate-slug']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes', data: [
            'name' => 'Otro',
            'slug' => 'duplicate-slug',
            'type' => 'text',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function update_modifies_attribute(): void
    {
        $attribute = $this->makeAttribute(['name' => 'Original', 'type' => 'text']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes/'.$attribute->id, data: [
            'name' => 'Renombrado',
            'is_filterable' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.is_filterable', expect: false);
    }

    #[Test]
    public function destroy_deletes_when_no_products_assigned(): void
    {
        $attribute = $this->makeAttribute();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes/'.$attribute->id);

        $response->assertNoContent();
        $this->assertNull(ProductAttribute::query()->find($attribute->id));
    }

    #[Test]
    public function destroy_blocks_when_assigned_to_products(): void
    {
        $attribute = $this->makeAttribute();

        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'currency_id' => Currency::factory()->primary()->create()->id,
            'is_active' => true,
        ]);

        DB::table('attribute_product')->insert([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value' => 'Rojo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/attributes/'.$attribute->id);

        $response->assertStatus(status: 500);
        $response->assertJsonFragment(['message' => 'No podés eliminar el atributo porque está asignado a productos. Reasigná o eliminá los valores primero.']);

        $this->assertNotNull(ProductAttribute::query()->find($attribute->id));
    }
}
