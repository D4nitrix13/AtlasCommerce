<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Inventory;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Catalog\Product;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del controller admin `Api\Admin\Inventory\StockController`.
 */
final class StockApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Store $store;

    private string $host = 'admin-stock.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $role = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        $this->user = UserModel::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'stock@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => 'secret',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($this->store->id);
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/inventory/stock");

        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_returns_stock_records(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'current_stock' => 50,
            'minimum_stock' => 10,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/inventory/stock");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.current_stock', expect: 50);
        $response->assertJsonPath(path: 'data.0.available_stock', expect: 50);
    }

    #[TestAttr]
    public function index_filters_by_low_stock_flag(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'current_stock' => 5,
            'minimum_stock' => 10,
        ]);
        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => Product::factory(),
            'current_stock' => 100,
            'minimum_stock' => 10,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/inventory/stock?low=1");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(path: 'data.0.is_low', expect: true);
    }

    #[TestAttr]
    public function update_applies_delta_and_records_movement(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stock = Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'current_stock' => 50,
            'minimum_stock' => 10,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://{$this->host}/api/v1/admin/inventory/stock/{$stock->id}",
            data: [
                'adjustment' => -10,
                'reason' => 'merma',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.current_stock', expect: 40);

        $this->assertDatabaseHas(table: 'inventory_movements', data: [
            'inventory_id' => $stock->id,
            'movement_type' => 'adjustment',
            'previous_stock' => 50,
            'new_stock' => 40,
            'quantity' => -10,
        ]);
    }

    #[TestAttr]
    public function update_rejects_adjustment_that_would_make_stock_negative(): void
    {
        $stock = Inventory::factory()->create(['current_stock' => 5]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://{$this->host}/api/v1/admin/inventory/stock/{$stock->id}",
            data: ['adjustment' => -20],
        );

        $response->assertStatus(status: 422);
    }
}
