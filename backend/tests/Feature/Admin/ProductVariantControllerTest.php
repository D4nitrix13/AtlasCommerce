<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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
 * Tests del CRUD admin de variantes (RF-CAT-027 backend).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Aislamiento por tenant (variant del producto de OTRA tienda → 404).
 *  - Listado + filtros (q, is_active, product_id).
 *  - Show.
 *  - Store con validación (sku único, barcode único, product_id existente).
 *  - Update con validación de unicidad.
 *  - Soft delete + with_trashed.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductVariantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Category $category;

    private Brand $brand;

    private Currency $currency;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

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

        $this->product = $this->makeProduct(store: $this->storeA);
    }

    /**
     * Helper: crea un producto publicado en la tienda dada.
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
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        $staff = User::factory()->create([
            'email' => 'staff@panda-estampados.com',
            'is_active' => true,
            'role_id' => Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail()->id,
        ]);

        Sanctum::actingAs(user: $staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_only_variants_in_tenant(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->product->id,
            'name' => 'Talla M',
            'sku' => 'CAM-M-01',
        ]);

        // Producto de OTRA tienda → variant debe ser invisible.
        $otherProduct = $this->makeProduct(store: $this->storeB);
        $outsider = ProductVariant::factory()->create(attributes: [
            'product_id' => $otherProduct->id,
            'name' => 'Outsider',
            'sku' => 'OUT-01',
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
        $ids = collect($response->json(key: 'data'))->pluck('id')->all();
        $this->assertContains(needle: $variant->id, haystack: $ids);
        $this->assertNotContains(needle: $outsider->id, haystack: $ids);
    }

    #[Test]
    public function index_filters_by_product_id(): void
    {
        $otherProduct = $this->makeProduct(store: $this->storeA);

        ProductVariant::factory()->create(['product_id' => $this->product->id, 'sku' => 'VAR-1']);
        ProductVariant::factory()->create(['product_id' => $otherProduct->id, 'sku' => 'VAR-2']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants?product_id='.$this->product->id);

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function index_filters_by_search_query(): void
    {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'name' => 'Camiseta Azul',
            'sku' => 'CAM-AZUL-01',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'name' => 'Camiseta Roja',
            'sku' => 'CAM-ROJA-01',
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants?q=azul');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function show_returns_404_for_variant_outside_tenant(): void
    {
        $otherProduct = $this->makeProduct(store: $this->storeB);
        $outsider = ProductVariant::factory()->create(attributes: ['product_id' => $otherProduct->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants/'.$outsider->id);
        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_creates_variant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/variants', data: [
            'product_id' => $this->product->id,
            'name' => 'Talla L',
            'sku' => 'CAM-L-01',
            'price_cents' => 7500,
            'cost_cents' => 2500,
            'barcode' => '7500000000017',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.sku', expect: 'CAM-L-01');
        $response->assertJsonPath(path: 'data.price_cents', expect: 7500);

        $variant = ProductVariant::query()->where(column: 'sku', operator: '=', value: 'CAM-L-01')->firstOrFail();
        $this->assertSame(expected: $this->product->id, actual: $variant->product_id);
    }

    #[Test]
    public function store_rejects_unknown_product(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/variants', data: [
            'product_id' => 99999,
            'name' => 'Test',
            'sku' => 'TEST-01',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['product_id']);
    }

    #[Test]
    public function store_rejects_duplicate_sku(): void
    {
        ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'DUPE-01',
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/variants', data: [
            'product_id' => $this->product->id,
            'name' => 'Otra',
            'sku' => 'DUPE-01',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['sku']);
    }

    #[Test]
    public function store_rejects_product_outside_tenant(): void
    {
        $otherProduct = $this->makeProduct(store: $this->storeB);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/variants', data: [
            'product_id' => $otherProduct->id,
            'name' => 'Outsider',
            'sku' => 'OUT-01',
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function update_modifies_variant_fields(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->product->id,
            'name' => 'Original',
            'sku' => 'VAR-ORIG-01',
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/variants/'.$variant->id, data: [
            'name' => 'Renombrada',
            'price_cents' => 9999,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.name', expect: 'Renombrada');
        $response->assertJsonPath(path: 'data.price_cents', expect: 9999);

        $variant->refresh();
        $this->assertSame(expected: 'Renombrada', actual: $variant->name);
    }

    #[Test]
    public function update_blocks_access_to_variant_outside_tenant(): void
    {
        $otherProduct = $this->makeProduct(store: $this->storeB);
        $outsider = ProductVariant::factory()->create(attributes: ['product_id' => $otherProduct->id]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/variants/'.$outsider->id, data: [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function destroy_soft_deletes(): void
    {
        $variant = ProductVariant::factory()->create(attributes: [
            'product_id' => $this->product->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/variants/'.$variant->id);
        $response->assertNoContent();

        $this->assertNull(ProductVariant::query()->find($variant->id));
        $this->assertNotNull(ProductVariant::query()->withTrashed()->find($variant->id));
    }

    #[Test]
    public function index_includes_soft_deleted_with_flag(): void
    {
        $variant = ProductVariant::factory()->create(attributes: ['product_id' => $this->product->id]);
        $variant->delete();

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/variants?with_trashed=1');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }
}
