<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductAttribute;
use App\Models\Catalog\ProductAttributeValue;
use App\Models\Stores\ProductStore;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del CRUD admin de ProductAttributeValue (RF-CAT-027 parte 2).
 *
 * Cubre:
 *  - Auth (401, 403 sin catalog.manage).
 *  - Listado + filtros (product_id, attribute_id) + aislamiento por tenant.
 *  - Show + 404 para valores fuera de scope.
 *  - Store con validación (product_id, attribute_id, value).
 *  - Store bloqueado si producto está fuera de scope.
 *  - UNIQUE(product_id, attribute_id): no se puede asignar dos veces.
 *  - Update + destroy.
 */
#[Group('admin')]
#[Group('catalog')]
final class ProductAttributeValueControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $storeA;

    private Store $storeB;

    private Product $product;

    private Product $otherProduct;

    private ProductAttribute $attribute;

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

        $this->attribute = ProductAttribute::query()->create(attributes: [
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);
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

    private function makeAssignment(int $productId, int $attributeId, string $value): ProductAttributeValue
    {
        return ProductAttributeValue::query()->create(attributes: [
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'value' => $value,
        ]);
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values');
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

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values');
        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_filters_by_product_id_and_tenant(): void
    {
        $this->makeAssignment($this->product->id, $this->attribute->id, 'Rojo');
        $this->makeAssignment($this->otherProduct->id, $this->attribute->id, 'Azul');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
        $value = $response->json(key: 'data.0.value');
        $this->assertSame(expected: 'Rojo', actual: $value);
    }

    #[Test]
    public function index_returns_403_for_product_outside_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values?product_id='.$this->otherProduct->id);

        $response->assertStatus(status: 403);
    }

    #[Test]
    public function index_filters_by_attribute_id(): void
    {
        $otherAttribute = ProductAttribute::query()->create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'text',
        ]);
        $this->makeAssignment($this->product->id, $this->attribute->id, 'Rojo');
        $this->makeAssignment($this->product->id, $otherAttribute->id, 'Algodón');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values?attribute_id='.$this->attribute->id);

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[Test]
    public function show_returns_404_for_value_outside_tenant(): void
    {
        $value = $this->makeAssignment($this->otherProduct->id, $this->attribute->id, 'Secreto');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values/'.$value->id);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_creates_assignment(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->attribute->id,
            'value' => 'Rojo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.value', expect: 'Rojo');

        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->attribute->id,
            'value' => 'Rojo',
        ]);
    }

    #[Test]
    public function store_rejects_duplicate_attribute_for_same_product(): void
    {
        $this->makeAssignment($this->product->id, $this->attribute->id, 'Rojo');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->attribute->id,
            'value' => 'Azul',
        ]);

        $response->assertStatus(status: 409);
        $response->assertJsonPath(path: 'message', expect: 'Este atributo ya está asignado a este producto.');
    }

    #[Test]
    public function store_rejects_product_outside_tenant(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values', data: [
            'product_id' => $this->otherProduct->id,
            'attribute_id' => $this->attribute->id,
            'value' => 'Rojo',
        ]);

        $response->assertStatus(status: 404);
    }

    #[Test]
    public function store_rejects_unknown_attribute(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values', data: [
            'product_id' => $this->product->id,
            'attribute_id' => 99999,
            'value' => 'Rojo',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['attribute_id']);
    }

    #[Test]
    public function store_rejects_empty_value(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->attribute->id,
            'value' => '',
        ]);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['value']);
    }

    #[Test]
    public function update_modifies_value(): void
    {
        $assignment = $this->makeAssignment($this->product->id, $this->attribute->id, 'Original');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values/'.$assignment->id, data: [
            'value' => 'Modificado',
        ]);

        $response->assertOk();
        $response->assertJsonPath(path: 'data.value', expect: 'Modificado');

        $assignment->refresh();
        $this->assertSame(expected: 'Modificado', actual: $assignment->value);
    }

    #[Test]
    public function destroy_deletes_assignment(): void
    {
        $assignment = $this->makeAssignment($this->product->id, $this->attribute->id, 'Borrame');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values/'.$assignment->id);

        $response->assertNoContent();
        $this->assertNull(ProductAttributeValue::query()->find($assignment->id));
    }

    #[Test]
    public function destroy_blocks_for_assignment_outside_tenant(): void
    {
        $assignment = $this->makeAssignment($this->otherProduct->id, $this->attribute->id, 'Secreto');

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->deleteJson(uri: 'http://panda-estampados.com/api/v1/admin/attribute-values/'.$assignment->id);

        $response->assertStatus(status: 404);
    }
}