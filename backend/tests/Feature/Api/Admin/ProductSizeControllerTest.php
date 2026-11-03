<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductSize;
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
 * Tests del CRUD admin de ProductSize (tallas/capacidades de producto).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado filtrado por product_id + aislamiento por tenant (403).
 *  - Store de la taza de 11 oz (value + unit) y tallas de ropa sin valor.
 *  - UNIQUE(product_id, slug): slug duplicado → error claro.
 *  - Update de value/unit y destroy.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductSizeControllerTest extends TestCase
{
    use RefreshDatabase;

    private const string BASE_URL = 'http://panda-estampados.com/api/v1/admin/catalog/product-sizes';

    private User $admin;

    private Store $storeA;

    private Store $storeB;

    private Product $product;

    private Product $otherProduct;

    private Role $adminRole;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

        $currency = \App\Models\Settings\Currency::factory()->primary()->create();
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();

        $this->storeA = Store::factory()->for($currency, 'currency')->create([
            'slug' => 'panda-estampados',
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($currency, 'currency')->create([
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
        $this->otherProduct = $this->makeProduct(store: $this->storeB);
    }

    private function makeProduct(Store $store, array $attrs = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'currency_id' => \App\Models\Settings\Currency::factory()->primary()->create()->id,
            'is_active' => true,
            'published_at' => now(),
        ], $attrs));

        ProductStore::query()->create(attributes: [
            'product_id' => $product->id,
            'store_id' => $store->id,
            'is_visible' => true,
        ]);

        return $product;
    }

    private function makeSize(int $productId, array $attrs = []): ProductSize
    {
        return ProductSize::query()->create(attributes: array_merge([
            'product_id' => $productId,
            'slug' => 'm',
            'label' => 'Medium',
            'position' => 0,
        ], $attrs));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: self::BASE_URL);
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        $staff = User::factory()->create([
            'is_active' => true,
            'role_id' => Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail()->id,
        ]);

        Sanctum::actingAs(user: $staff, abilities: ['*']);

        $response = $this->getJson(uri: self::BASE_URL);
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_filters_by_product_id_and_tenant(): void
    {
        $this->makeSize($this->product->id, ['slug' => '11-oz', 'label' => '11 oz', 'value' => 11, 'unit' => 'oz']);
        $this->makeSize($this->otherProduct->id, ['slug' => '15-oz', 'label' => '15 oz', 'value' => 15, 'unit' => 'oz']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: self::BASE_URL.'?product_id='.$this->product->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.slug', expect: '11-oz');
        $response->assertJsonPath(path: 'data.0.value', expect: 11);
        $response->assertJsonPath(path: 'data.0.unit', expect: 'oz');
    }

    #[Test]
    public function index_returns_403_for_product_outside_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: self::BASE_URL.'?product_id='.$this->otherProduct->id);

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_orders_by_position(): void
    {
        $this->makeSize($this->product->id, ['slug' => 'l', 'label' => 'Large', 'position' => 2]);
        $this->makeSize($this->product->id, ['slug' => 's', 'label' => 'Small', 'position' => 0]);
        $this->makeSize($this->product->id, ['slug' => 'm', 'label' => 'Medium', 'position' => 1]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: self::BASE_URL.'?product_id='.$this->product->id);

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath(path: 'data.0.slug', expect: 's');
        $response->assertJsonPath(path: 'data.1.slug', expect: 'm');
        $response->assertJsonPath(path: 'data.2.slug', expect: 'l');
    }

    #[Test]
    public function store_creates_cup_size_with_value_and_unit(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'label' => '11 oz',
            'value' => 11,
            'unit' => 'oz',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: '11-oz');
        $response->assertJsonPath(path: 'data.label', expect: '11 oz');
        $response->assertJsonPath(path: 'data.value', expect: 11);
        $response->assertJsonPath(path: 'data.unit', expect: 'oz');

        $this->assertDatabaseHas(table: 'product_sizes', data: [
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'label' => '11 oz',
            'unit' => 'oz',
        ]);
    }

    #[Test]
    public function store_creates_clothing_size_without_value_and_unit(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'slug' => 'm',
            'label' => 'Medium',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.slug', expect: 'm');
        $response->assertJsonPath(path: 'data.value', expect: null);
        $response->assertJsonPath(path: 'data.unit', expect: null);
    }

    #[Test]
    public function store_rejects_duplicate_slug_for_same_product(): void
    {
        $this->makeSize($this->product->id, ['slug' => '11-oz', 'label' => '11 oz', 'value' => 11, 'unit' => 'oz']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'label' => '11 oz duplicada',
            'value' => 11,
            'unit' => 'oz',
        ]);

        $response->assertStatus(status: 409);
        $response->assertJsonPath(path: 'message', expect: 'Ya existe una talla con este slug para este producto.');
    }

    #[Test]
    public function store_rejects_product_outside_tenant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->otherProduct->id,
            'slug' => 'm',
            'label' => 'Medium',
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_rejects_invalid_slug(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'slug' => 'Invalid Slug!',
            'label' => 'Medium',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['slug']);
    }

    #[Test]
    public function store_rejects_unknown_unit(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'slug' => '11-oz',
            'label' => '11 oz',
            'value' => 11,
            'unit' => 'gal',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['unit']);
    }

    #[Test]
    public function update_changes_value_and_unit(): void
    {
        $size = $this->makeSize($this->product->id, ['slug' => '11-oz', 'label' => '11 oz', 'value' => 11, 'unit' => 'oz']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: self::BASE_URL.'/'.$size->id, data: [
            'value' => 15,
            'unit' => 'oz',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.value', expect: 15);
        $response->assertJsonPath(path: 'data.unit', expect: 'oz');

        $size->refresh();
        $this->assertSame(expected: 15.0, actual: $size->value);
        $this->assertSame(expected: 'oz', actual: $size->unit);
    }

    #[Test]
    public function update_blocks_for_size_outside_tenant(): void
    {
        $size = $this->makeSize($this->otherProduct->id, ['slug' => '15-oz', 'label' => '15 oz', 'value' => 15, 'unit' => 'oz']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: self::BASE_URL.'/'.$size->id, data: [
            'value' => 20,
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function destroy_deletes_size(): void
    {
        $size = $this->makeSize($this->product->id, ['slug' => 'borrame', 'label' => 'Borrame']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: self::BASE_URL.'/'.$size->id);

        $response->assertNoContent();
        $this->assertNull(ProductSize::query()->find($size->id));
    }

    #[Test]
    public function destroy_blocks_for_size_outside_tenant(): void
    {
        $size = $this->makeSize($this->otherProduct->id, ['slug' => 'secreto', 'label' => 'Secreto']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: self::BASE_URL.'/'.$size->id);

        $response->assertStatus(status: 404);
    }
}
