<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de imágenes de producto (RF-CAT-022).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado + filtros (is_primary) + aislamiento por tenant.
 *  - Show + 404 fuera de scope o cross-product.
 *  - Store con validación (image_url requerida).
 *  - Store primary=true desmarca otras primarias.
 *  - sort_order auto-asignado si no se pasa.
 *  - Update + cross-product validation.
 *  - Destroy.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductImageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Role $adminRole;

    private Role $staffRole;

    private Store $storeA;

    private Store $storeB;

    private Product $product;

    private Product $otherProduct;

    private Category $category;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $currency = \App\Models\Settings\Currency::factory()->primary()->create();
        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();

        $storeASlug = 'panda-'.Str::uuid()->toString();
        $storeBSlug = 'kitsune-'.Str::uuid()->toString();

        $this->storeA = Store::factory()->for($currency, 'currency')->create([
            'slug' => $storeASlug,
        ]);
        StoreDomain::query()->create(attributes: [
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($currency, 'currency')->create([
            'slug' => $storeBSlug,
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
        $this->staff->stores()->syncWithoutDetaching(ids: [$this->storeA->id]);

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

    private function makeImage(Product $product, array $attrs = []): ProductImage
    {
        return ProductImage::query()->create(array_merge([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/img.jpg',
            'alt_text' => 'Test',
            'is_primary' => false,
            'sort_order' => 0,
        ], $attrs));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images');
        $response->assertStatus(status: 401);
    }

    #[Test]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_returns_images_for_product_in_tenant(): void
    {
        $this->makeImage($this->product, ['image_url' => 'https://a.jpg', 'sort_order' => 1]);
        $this->makeImage($this->product, ['image_url' => 'https://b.jpg', 'sort_order' => 2]);
        $this->makeImage($this->otherProduct, ['image_url' => 'https://c.jpg']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 2);
    }

    #[Test]
    public function index_returns_404_for_product_outside_tenant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->otherProduct->id.'/images');

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function index_filters_by_is_primary(): void
    {
        $this->makeImage($this->product, ['image_url' => 'https://a.jpg', 'is_primary' => true]);
        $this->makeImage($this->product, ['image_url' => 'https://b.jpg', 'is_primary' => false]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images?is_primary=1');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function show_returns_404_for_image_of_other_product(): void
    {
        $image = $this->makeImage($this->otherProduct);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_creates_image_with_auto_sort_order(): void
    {
        $this->makeImage($this->product, ['sort_order' => 5]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/new.jpg',
            'alt_text' => 'New',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.sort_order', expect: 6); // max(5) + 1
        $response->assertJsonPath(path: 'data.is_primary', expect: false);
    }

    #[Test]
    public function store_with_explicit_sort_order(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
            'sort_order' => 10,
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.sort_order', expect: 10);
    }

    #[Test]
    public function store_rejects_empty_image_url(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => '',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['image_url']);
    }

    #[Test]
    public function store_with_primary_unmarks_existing_primary(): void
    {
        $existing = $this->makeImage($this->product, ['is_primary' => true]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/new-primary.jpg',
            'is_primary' => true,
        ]);

        $response->assertCreated();

        // La imagen vieja ya NO es primaria.
        $existing->refresh();
        $this->assertFalse($existing->is_primary);

        // La nueva sí es primaria.
        $newId = $response->json(key: 'data.id');
        $new = ProductImage::query()->find($newId);
        $this->assertTrue($new->is_primary);
    }

    #[Test]
    public function store_blocks_for_product_outside_tenant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->otherProduct->id.'/images', data: [
            'image_url' => 'https://cdn.example.com/x.jpg',
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function update_modifies_image_fields(): void
    {
        $image = $this->makeImage($this->product, ['alt_text' => 'Original']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images/'.$image->id, data: [
            'alt_text' => 'Renombrado',
            'sort_order' => 99,
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.alt_text', expect: 'Renombrado');
        $response->assertJsonPath(path: 'data.sort_order', expect: 99);
    }

    #[Test]
    public function update_setting_primary_unmarks_existing(): void
    {
        $old = $this->makeImage($this->product, ['is_primary' => true]);
        $new = $this->makeImage($this->product, ['is_primary' => false]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images/'.$new->id, data: [
            'is_primary' => true,
        ]);

        $response->assertOk();

        $old->refresh();
        $new->refresh();
        $this->assertFalse($old->is_primary);
        $this->assertTrue($new->is_primary);
    }

    #[Test]
    public function destroy_deletes_image(): void
    {
        $image = $this->makeImage($this->product);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertNoContent();
        $this->assertNull(ProductImage::query()->find($image->id));
    }

    #[Test]
    public function destroy_blocks_cross_product(): void
    {
        $image = $this->makeImage($this->otherProduct);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/admin/products/'.$this->product->id.'/images/'.$image->id);

        $response->assertStatus(status: 404);
    }
}
