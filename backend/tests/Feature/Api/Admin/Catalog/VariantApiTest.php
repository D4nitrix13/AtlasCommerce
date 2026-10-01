<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Catalog;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
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
 * Tests del CRUD admin de variantes en /api/v1/admin/catalog/variants (RF-CAT-027).
 *
 * Las variantes heredan tenant scope del product padre. Si el product
 * no está publicado en ninguna tienda del admin, el variant no es
 * accesible (404).
 *
 * Cubre:
 *  - Auth + permisos.
 *  - Index + filtros (q, is_active, product_id) + tenant isolation.
 *  - Show + 404 cross-tenant.
 *  - Store con validación (sku único, product_id existente).
 *  - Update con validación de unicidad.
 *  - Soft delete.
 */
#[Group('admin')]
#[Group('catalog')]
#[Group('catalog-namespace')]
final class VariantApiTest extends TestCase
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
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants");

        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants");

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_only_variants_in_tenant_scope(): void
    {
        ProductVariant::factory()->create(['product_id' => $this->product->id]);

        $outsiderProduct = $this->makeProduct(store: $this->storeB);
        ProductVariant::factory()->create(['product_id' => $outsiderProduct->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_filters_by_product_id(): void
    {
        $otherProduct = $this->makeProduct(store: $this->storeA);
        ProductVariant::factory()->create(['product_id' => $this->product->id]);
        ProductVariant::factory()->create(['product_id' => $otherProduct->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants?product_id={$this->product->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function show_returns_variant(): void
    {
        $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants/{$variant->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.id', expect: $variant->id);
    }

    #[Test]
    public function show_returns_404_for_variant_outside_tenant(): void
    {
        $outsiderProduct = $this->makeProduct(store: $this->storeB);
        $outsiderVariant = ProductVariant::factory()->create(['product_id' => $outsiderProduct->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants/{$outsiderVariant->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function store_creates_variant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants", data: [
            'product_id' => $this->product->id,
            'name' => 'Rojo XL',
            'sku' => 'VAR-RED-XL-001',
            'price_cents' => 12000,
            'cost_cents' => 5000,
            'barcode' => '7701234567890',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.sku', expect: 'VAR-RED-XL-001');
    }

    #[Test]
    public function store_rejects_duplicate_sku(): void
    {
        ProductVariant::factory()->create(['product_id' => $this->product->id, 'sku' => 'DUP-SKU']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants", data: [
            'product_id' => $this->product->id,
            'name' => 'Dup',
            'sku' => 'DUP-SKU',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['sku']);
    }

    #[Test]
    public function store_rejects_unknown_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants", data: [
            'product_id' => 99999,
            'name' => 'Variant',
            'sku' => 'VAR-X',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['product_id']);
    }

    #[Test]
    public function update_modifies_variant(): void
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'price_cents' => 5000,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants/{$variant->id}", data: [
            'price_cents' => 9999,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.price_cents', expect: 9999);
    }

    #[Test]
    public function update_returns_404_for_variant_outside_tenant(): void
    {
        $outsiderProduct = $this->makeProduct(store: $this->storeB);
        $outsiderVariant = ProductVariant::factory()->create(['product_id' => $outsiderProduct->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants/{$outsiderVariant->id}", data: [
            'price_cents' => 100,
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function destroy_soft_deletes_variant(): void
    {
        $variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: "http://{$this->host}/api/v1/admin/catalog/variants/{$variant->id}");

        $response->assertNoContent();
        $this->assertNull(ProductVariant::query()->find($variant->id));
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
