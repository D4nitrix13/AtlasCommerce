<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Metrics;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests para `GET /api/v1/admin/metrics/products/top`.
 *
 * Casos:
 *   - Shape: data array con { id, name, units_sold, revenue_cents, revenue_label }
 *   - Limite: respeta ?limit=N (default 5, max 20)
 *   - Periodo: respeta ?period_days=N (default 30)
 *   - Filtra orders cancelled (no revenue real)
 *   - Orden: descendente por revenue
 */
final class TopProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Store $store;

    private Currency $currency;

    private string $host = 'admin.panda-estampados.test';

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for($this->currency, 'currency')->create();

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
            'email' => 'top-products@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => Hash::make(value: 'secret'),
            'phone' => '5555555555',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($this->store->id);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);
    }

    public function test_returns_top_products_with_correct_shape(): void
    {
        $product1 = Product::factory()->create(['name' => 'Camiseta A']);
        $product2 = Product::factory()->create(['name' => 'Taza B']);
        $customer = Customer::factory()->create(['user_id' => null]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
            'status' => 'completed',
            'ordered_at' => now()->subDays(5),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 3,
            'unit_price_cents' => 3000,
            'subtotal_cents' => 9000,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price_cents' => 2000,
            'subtotal_cents' => 2000,
        ]);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/products/top");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'units_sold', 'revenue_cents', 'revenue_label'],
            ],
            'meta' => ['period_days', 'limit'],
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Orden descendente por revenue.
        $this->assertSame($product1->id, $data[0]['id']);
        $this->assertSame(3, $data[0]['units_sold']);
        $this->assertSame(9000, $data[0]['revenue_cents']);
        $this->assertSame(1, $data[1]['units_sold']);
        $this->assertSame(2000, $data[1]['revenue_cents']);
    }

    public function test_filters_out_cancelled_orders(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $customer = Customer::factory()->create(['user_id' => null]);

        // Order cancelled NO debe contar.
        $cancelledOrder = Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
            'status' => 'cancelled',
            'ordered_at' => now()->subDays(5),
        ]);
        OrderItem::query()->create([
            'order_id' => $cancelledOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price_cents' => 5000,
            'subtotal_cents' => 25000,
        ]);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/products/top");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    public function test_respects_limit_query_param(): void
    {
        $customer = Customer::factory()->create(['user_id' => null]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
            'status' => 'completed',
            'ordered_at' => now()->subDays(5),
        ]);

        // 3 productos, ?limit=2 → debe devolver 2
        for ($i = 0; $i < 3; $i++) {
            $p = Product::factory()->create(['name' => "Product $i"]);
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $p->id,
                'quantity' => 1,
                'unit_price_cents' => 1000 * ($i + 1),
                'subtotal_cents' => 1000 * ($i + 1),
            ]);
        }

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/products/top?limit=2");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/products/top");
        $this->assertSame(401, $response->getStatusCode());
    }
}
