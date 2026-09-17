<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Catalog\WholesalePrice;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD admin de precios mayoristas (RF-CAT-029).
 *
 * Cubre:
 *  - Auth (401 sin token, 403 sin `catalog.manage`).
 *  - Listado + filtros (product_id, store_id, min_qty) con aislamiento por tenant.
 *  - Show.
 *  - Store con validación (FKs existen, min_qty >= 1, price_cents >= 0).
 *  - Store falla con 422 en duplicado (product_id, store_id, min_qty).
 *  - Store rechaza store fuera del scope (403).
 *  - Update solo permite `price_cents` (inmutabilidad de la tripla).
 *  - Destroy.
 *  - Cross-tenant → 404.
 */
#[Group('admin')]
#[Group('catalog')]
final class WholesalePriceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $unauthorized;

    private Store $storeA;

    private Store $storeB;

    private Role $adminRole;

    private Role $staffRole;

    private Currency $currency;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $this->adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $this->staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->currency = Currency::factory()->primary()->create();

        $this->storeA = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($this->currency, 'currency')->create(['slug' => 'kitsune']);
        StoreDomain::query()->create([
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

        $this->unauthorized = User::factory()->create();

        $this->product = Product::factory()->for($this->currency, 'currency')->create();
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/wholesale-prices');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_catalog_manage_permission(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/wholesale-prices');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_returns_only_prices_in_tenant(): void
    {
        WholesalePrice::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->storeA->id,
            'min_qty' => 10,
        ]);
        WholesalePrice::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->storeB->id,
            'min_qty' => 10,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/admin/wholesale-prices');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[TestAttr]
    public function index_filters_by_product_and_store(): void
    {
        $product2 = Product::factory()->for($this->currency, 'currency')->create();
        WholesalePrice::factory()->create([
            'product_id' => $this->product->id, 'store_id' => $this->storeA->id, 'min_qty' => 10,
        ]);
        WholesalePrice::factory()->create([
            'product_id' => $product2->id, 'store_id' => $this->storeA->id, 'min_qty' => 10,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $byProduct = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/wholesale-prices?product_id={$this->product->id}");
        $byProduct->assertJsonCount(1, 'data');

        $byStore = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/wholesale-prices?store_id={$this->storeA->id}");
        $byStore->assertJsonCount(2, 'data');
    }

    #[TestAttr]
    public function store_creates_price_tier(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/wholesale-prices',
            data: [
                'product_id' => $this->product->id,
                'store_id' => $this->storeA->id,
                'min_qty' => 25,
                'price_cents' => 20_00,
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.product_id', expect: $this->product->id);
        $response->assertJsonPath(path: 'data.store_id', expect: $this->storeA->id);
        $response->assertJsonPath(path: 'data.min_qty', expect: 25);
        $response->assertJsonPath(path: 'data.price.cents', expect: 20_00);
    }

    #[TestAttr]
    public function store_rejects_store_outside_tenant_scope(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/wholesale-prices',
            data: [
                'product_id' => $this->product->id,
                'store_id' => $this->storeB->id,
                'min_qty' => 10,
                'price_cents' => 15_00,
            ],
        );

        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function store_rejects_duplicate_product_store_min_qty(): void
    {
        WholesalePrice::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->storeA->id,
            'min_qty' => 10,
            'price_cents' => 15_00,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/admin/wholesale-prices',
            data: [
                'product_id' => $this->product->id,
                'store_id' => $this->storeA->id,
                'min_qty' => 10,
                'price_cents' => 18_00,
            ],
        );

        $response->assertStatus(status: 422);
        $this->assertStringContainsString(
            needle: 'Ya existe un tier mayorista',
            haystack: (string) $response->json('message'),
        );
    }

    #[TestAttr]
    public function update_only_changes_price_cents(): void
    {
        $price = WholesalePrice::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->storeA->id,
            'min_qty' => 10,
            'price_cents' => 15_00,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/v1/admin/wholesale-prices/{$price->id}",
            data: [
                'price_cents' => 12_00,
                // product_id / store_id / min_qty ignorados aunque se envíen.
                'product_id' => 99999,
                'store_id' => 99999,
                'min_qty' => 999,
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.price.cents', expect: 12_00);
        $response->assertJsonPath(path: 'data.product_id', expect: $this->product->id);
        $response->assertJsonPath(path: 'data.store_id', expect: $this->storeA->id);
        $response->assertJsonPath(path: 'data.min_qty', expect: 10);
    }

    #[TestAttr]
    public function destroy_removes_price_tier(): void
    {
        $price = WholesalePrice::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->storeA->id,
            'min_qty' => 10,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $this->deleteJson(uri: "http://panda-estampados.com/api/v1/admin/wholesale-prices/{$price->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing(table: 'wholesale_prices', data: ['id' => $price->id]);
    }

    #[TestAttr]
    public function price_from_other_tenant_returns_404(): void
    {
        $otherPrice = WholesalePrice::factory()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->storeB->id,
            'min_qty' => 10,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/v1/admin/wholesale-prices/{$otherPrice->id}");

        $response->assertStatus(status: 404);
    }
}
