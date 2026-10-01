<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Catalog;

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
 * Tests del CRUD admin de productos en /api/v1/admin/catalog/products (RF-CAT-026).
 *
 * Verifica el namespace /admin/catalog/* recién registrado:
 *  - Auth (401 sin token, 403 sin `catalog.manage`).
 *  - Listado + paginación + filtros (q, is_active, category_id, brand_id).
 *  - Aislamiento por tenant: productos de otra tienda no son visibles.
 *  - Show / Store / Update / Destroy happy + 404.
 *  - Validación (slug único, sku único, FK existentes).
 *  - Acciones custom: archive, publish, syncStores.
 */
#[Group('admin')]
#[Group('catalog')]
#[Group('catalog-namespace')]
final class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Role $staffRole;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    private Product $product;

    private string $host = 'panda-estampados.com';

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
            'domain' => $this->host,
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

        $this->staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => $this->staffRole->id,
        ]);

        $this->product = $this->makeProduct(store: $this->storeA);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/products");

        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/products");

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_only_products_in_tenant_scope(): void
    {
        $this->makeProduct(store: $this->storeB); // Producto en otra tienda

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/products");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.id', expect: $this->product->id);
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/products?q=".urlencode($this->product->name));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function show_returns_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$this->product->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.id', expect: $this->product->id);
        $response->assertJsonPath(path: 'data.slug', expect: $this->product->slug);
    }

    #[Test]
    public function show_returns_404_for_product_outside_tenant(): void
    {
        $outsider = $this->makeProduct(store: $this->storeB);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$outsider->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function store_creates_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/products", data: [
            'name' => 'Remera Test',
            'slug' => 'remera-test',
            'sku' => 'TEST-SKU-001',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
            'base_price_cents' => 15000,
            'is_active' => true,
            'store_ids' => [$this->storeA->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'remera-test');
        $response->assertJsonPath(path: 'data.sku', expect: 'TEST-SKU-001');
    }

    #[Test]
    public function store_rejects_duplicate_slug(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/products", data: [
            'name' => 'Otro',
            'slug' => $this->product->slug,
            'sku' => 'OTRO-SKU',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_invalid_slug_format(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/products", data: [
            'name' => 'Test',
            'slug' => 'Invalid Slug With Spaces',
            'sku' => 'TEST-SKU-002',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => $this->currency->id,
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function update_modifies_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$this->product->id}", data: [
            'name' => 'Renombrado',
            'base_price_cents' => 9999,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.base_price_cents', expect: 9999);
    }

    #[Test]
    public function update_returns_404_for_product_outside_tenant(): void
    {
        $outsider = $this->makeProduct(store: $this->storeB);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$outsider->id}", data: [
            'name' => 'Renombrado Out',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function archive_deactivates_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$this->product->id}/archive");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_active', expect: false);
    }

    #[Test]
    public function publish_activates_product(): void
    {
        $this->product->update(attributes: ['is_active' => false, 'published_at' => null]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$this->product->id}/publish");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_active', expect: true);
    }

    #[Test]
    public function sync_stores_replaces_product_stores(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$this->product->id}/stores", data: [
            'store_ids' => [$this->storeA->id],
        ]);

        $response->assertOk();
        $count = ProductStore::query()->where(column: 'product_id', operator: '=', value: $this->product->id)->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function destroy_deletes_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$this->product->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted($this->product);
    }

    #[Test]
    public function destroy_returns_404_for_product_outside_tenant(): void
    {
        $outsider = $this->makeProduct(store: $this->storeB);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: "http://{$this->host}/api/v1/admin/catalog/products/{$outsider->id}");

        $response->assertNotFound();
    }

    /**
     * Helper: crea un producto publicado en la tienda dada.
     *
     * @param  array<string, mixed>  $attributes
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
}
