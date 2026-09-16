<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de productos (RF-CAT-026).
 *
 * Cubre:
 *  - Auth (401, 403 sin `catalog.manage`).
 *  - Listado + aislamiento por tenant.
 *  - Show + 404 para productos fuera del scope.
 *  - Store con validación (slug único, sku único, FKs existentes).
 *  - Update + validación de unicidad.
 *  - Archive / Publish (soft state via is_active + published_at).
 *  - Sync de stores atómico con validación de scope.
 *  - Destroy con cleanup de FKs.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Role $staffRole;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->currency = Currency::factory()->primary()->create();
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();

        $this->storeA = Store::factory()->for($this->currency, 'currency')->create([
            'slug' => 'panda-estampados',
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($this->currency, 'currency')->create([
            'slug' => 'kitsune',
        ]);
        StoreDomain::query()->create(attributes: [
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

        $this->nonAdmin = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);
        $this->nonAdmin->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);
    }

    /**
     * Helper: crea un producto ya publicado y visible en la tienda dada.
     */
    private function makeProduct(Store $store, array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'is_active' => true,
            'published_at' => now(),
        ], $attributes));

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->nonAdmin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_paginated_products_in_tenant(): void
    {
        $this->makeProduct(store: $this->storeA, attributes: ['name' => 'A']);
        $this->makeProduct(store: $this->storeA, attributes: ['name' => 'B']);

        // Producto en otra tienda — NO debe aparecer.
        $outsider = $this->makeProduct(store: $this->storeB, attributes: ['name' => 'Outsider']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 2);
        $ids = collect($response->json(key: 'data'))->pluck('id')->all();
        $this->assertNotContains(needle: $outsider->id, haystack: $ids);
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        $this->makeProduct(store: $this->storeA, attributes: [
            'name' => 'Camiseta Premium',
            'sku' => 'CAM-PREM-01',
        ]);
        $this->makeProduct(store: $this->storeA, attributes: [
            'name' => 'Gorra',
            'sku' => 'GOR-01',
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products?q=camiseta');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function show_returns_product_with_relations(): void
    {
        $product = $this->makeProduct(store: $this->storeA);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id);

        $response->assertOk();
        $response->assertJsonStructure(structure: [
            'data' => [
                'id',
                'name',
                'slug',
                'sku',
                'cost_cents',
                'base_price_cents',
                'stores',
            ],
        ]);
    }

    #[Test]
    public function show_returns_404_for_product_outside_tenant(): void
    {
        $outsider = $this->makeProduct(store: $this->storeB);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$outsider->id);
        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_creates_product_and_syncs_stores(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products', data: [
            'name' => 'Camiseta Nueva',
            'slug' => 'camiseta-nueva',
            'sku' => 'CAM-NEW-01',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'base_price_cents' => 5000,
            'cost_cents' => 1500,
            'description' => 'Camiseta de prueba',
            'store_ids' => [$this->storeA->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'camiseta-nueva');

        $product = Product::query()->where(column: 'slug', operator: '=', value: 'camiseta-nueva')->firstOrFail();
        $this->assertSame(expected: 5000, actual: $product->base_price_cents);
        $this->assertSame(expected: 1, actual: ProductStore::query()->where(column: 'product_id', operator: '=', value: $product->id)->count());
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products', data: [
            'name' => 'Test',
            'slug' => 'Invalid Slug',
            'sku' => 'TEST-01',
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        $this->makeProduct(store: $this->storeA, attributes: ['slug' => 'duplicate-slug', 'sku' => 'DUP-01']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products', data: [
            'name' => 'Otro',
            'slug' => 'duplicate-slug',
            'sku' => 'DUP-02',
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_duplicate_sku(): void
    {
        $this->makeProduct(store: $this->storeA, attributes: ['slug' => 'p-1', 'sku' => 'DUP-SKU']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products', data: [
            'name' => 'Otro',
            'slug' => 'p-2',
            'sku' => 'DUP-SKU',
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['sku']);
    }

    #[Test]
    public function store_rejects_unknown_category(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products', data: [
            'name' => 'X',
            'slug' => 'x-product',
            'sku' => 'X-01',
            'category_id' => 99999,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['category_id']);
    }

    #[Test]
    public function store_rejects_store_outside_admin_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products', data: [
            'name' => 'Producto Test',
            'slug' => 'x-store-scope',
            'sku' => 'XSS-01',
            'category_id' => $this->category->id,
            'store_ids' => [$this->storeB->id], // storeB no accesible
        ]);

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function update_modifies_product_fields(): void
    {
        $product = $this->makeProduct(store: $this->storeA, attributes: ['name' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id, data: [
            'name' => 'Renombrado',
            'base_price_cents' => 9999,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.base_price_cents', expect: 9999);

        $product->refresh();
        $this->assertSame(expected: 'Renombrado', actual: $product->name);
        $this->assertSame(expected: 9999, actual: $product->base_price_cents);
    }

    #[Test]
    public function archive_sets_is_active_false_and_clears_published_at(): void
    {
        $product = $this->makeProduct(store: $this->storeA, attributes: [
            'is_active' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id.'/archive');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_active', expect: false);
        $response->assertJsonPath(path: 'data.published_at', expect: null);
    }

    #[Test]
    public function publish_sets_is_active_true_and_published_at_to_now(): void
    {
        $product = $this->makeProduct(store: $this->storeA, attributes: [
            'is_active' => false,
            'published_at' => null,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id.'/publish');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_active', expect: true);
        $response->assertJsonStructure(structure: ['data' => ['published_at']]);
    }

    #[Test]
    public function sync_stores_replaces_set_atomically(): void
    {
        $product = $this->makeProduct(store: $this->storeA);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        // Sync con array vacío debe limpiar todas las tiendas.
        $response = $this->putJson(
            uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id.'/stores',
            data: ['store_ids' => []],
        );

        $response->assertOk();
        $this->assertSame(expected: 0, actual: ProductStore::query()->where(column: 'product_id', operator: '=', value: $product->id)->count());
    }

    #[Test]
    public function sync_stores_rejects_store_outside_scope(): void
    {
        $product = $this->makeProduct(store: $this->storeA);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id.'/stores',
            data: ['store_ids' => [$this->storeB->id]],
        );

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function destroy_soft_deletes_and_cleans_relations(): void
    {
        $product = $this->makeProduct(store: $this->storeA);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/products/'.$product->id);

        $response->assertNoContent();
        $this->assertNull(Product::query()->find($product->id));
        $this->assertSame(expected: 0, actual: ProductStore::query()->where(column: 'product_id', operator: '=', value: $product->id)->count());
    }

    #[Test]
    public function index_includes_soft_deleted_when_with_trashed_flag(): void
    {
        $product = $this->makeProduct(store: $this->storeA);
        $product->delete();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/products?with_trashed=1');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }
}
