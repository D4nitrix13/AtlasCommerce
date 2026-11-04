<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

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
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valores tipados de los atributos EAV del admin.
 *
 * `attribute_product` tiene `value` (texto), `value_number` y
 * `value_boolean`, pero hasta ahora el controller solo persistía el
 * texto. Sin las columnas tipadas no se puede filtrar u ordenar por un
 * número ("peso ≤ 0.5 kg") ni guardar un booleano ("apto para
 * microondas") sin perder el tipo en el camino.
 */
#[Group('admin')]
#[Group('catalog')]
#[Group('attributes')]
final class ProductAttributeValueTypedTest extends TestCase
{
    use RefreshDatabase;

    private const string BASE_URL = 'http://panda-estampados.com/api/v1/admin/catalog/product-attribute-values';

    private User $admin;

    private Product $product;

    private ProductAttribute $weight;

    private ProductAttribute $microwave;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $currency = \App\Models\Settings\Currency::factory()->primary()->create();
        $store = Store::factory()->for($currency, 'currency')->create([
            'slug' => 'panda-estampados',
        ]);
        StoreDomain::query()->create([
            'store_id' => $store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$store->id]);

        $this->product = Product::factory()->create([
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'currency_id' => $currency->id,
        ]);
        ProductStore::query()->create([
            'product_id' => $this->product->id,
            'store_id' => $store->id,
            'slug' => $this->product->slug,
            'is_visible' => true,
        ]);

        $this->weight = ProductAttribute::query()->create([
            'name' => 'Peso',
            'slug' => 'weight',
            'type' => 'number',
            'unit' => 'kg',
        ]);
        $this->microwave = ProductAttribute::query()->create([
            'name' => 'Apto para microondas',
            'slug' => 'microwave_safe',
            'type' => 'boolean',
        ]);

        Sanctum::actingAs($this->admin);
    }

    #[Test]
    public function it_persists_numeric_attribute_value(): void
    {
        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->weight->id,
            'value' => '0.35',
            'value_number' => 0.35,
        ]);

        $response->assertStatus(status: 201);
        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->weight->id,
            'value_number' => 0.35,
        ]);
    }

    #[Test]
    public function it_persists_boolean_attribute_value(): void
    {
        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->microwave->id,
            'value' => '1',
            'value_boolean' => true,
        ]);

        $response->assertStatus(status: 201);
        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->microwave->id,
            'value_boolean' => true,
        ]);
    }

    #[Test]
    public function it_exposes_typed_values_in_the_response(): void
    {
        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->weight->id,
            'value' => '0.35',
            'value_number' => 0.35,
        ]);

        $response->assertStatus(status: 201);
        $this->assertEqualsWithDelta(0.35, (float) ($response->json('data.value_number') ?? 0), 0.001);
    }

    #[Test]
    public function it_updates_typed_values(): void
    {
        $existing = ProductAttributeValue::query()->create([
            'product_id' => $this->product->id,
            'attribute_id' => $this->microwave->id,
            'value' => '0',
            'value_boolean' => false,
        ]);

        $response = $this->putJson(uri: self::BASE_URL.'/'.$existing->id, data: [
            'value_boolean' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'id' => $existing->id,
            'value_boolean' => true,
        ]);
    }

    #[Test]
    public function it_allows_saving_only_the_typed_value(): void
    {
        // Un atributo numérico no necesita el `value` textual: la fuente
        // de verdad es `value_number`.
        $response = $this->postJson(uri: self::BASE_URL, data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->weight->id,
            'value_number' => 1.5,
        ]);

        $response->assertStatus(status: 201);
        $this->assertDatabaseHas(table: 'attribute_product', data: [
            'product_id' => $this->product->id,
            'attribute_id' => $this->weight->id,
            'value_number' => 1.5,
        ]);
    }
}
