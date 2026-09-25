<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests para GET /api/v1/admin/orders/recent.
 *
 * Cubre:
 *   - Tenant scoping: solo orders del store actual (no cross-tenant).
 *   - Shape: cada row tiene {id, order_number, customer_name, product_summary,
 *     status_label, status_color, amount_label}.
 *   - Limite: respeta ?limit=N.
 *   - Auth: requiere Bearer Sanctum token (admin).
 */
final class RecentOrdersControllerTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $adminUser;

    private Store $store;

    private Store $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

        $currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()->for($currency, 'currency')->create();
        $this->otherStore = Store::factory()->for($currency, 'currency')->create();

        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'admin.panda-estampados.test',
            'type' => 'domain',
            'base_path' => '/',
            'is_active' => true,
            'is_primary' => true,
        ]);

        // Crear admin user SIN factory (la factory usa Laravel default
        // template que requiere columnas que no tenemos: 'name').
        $this->adminUser = UserModel::query()->create([
            'first_name' => 'Recent',
            'last_name' => 'Orders Admin',
            'email' => 'recent-orders-admin@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => Hash::make(value: 'secret-password'),
            'phone' => '5555555555',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);

        $this->adminUser->stores()->attach($this->store->id);

        Sanctum::actingAs(user: $this->adminUser, abilities: ['*']);
    }

    public function test_returns_recent_orders_for_current_tenant_only(): void
    {
        $customerInStore = $this->makeCustomer(stores: [$this->store]);
        $customerOtherStore = $this->makeCustomer(stores: [$this->otherStore]);

        $orderMine = $this->makeOrder(customer: $customerInStore, store: $this->store);
        $this->makeOrder(customer: $customerOtherStore, store: $this->otherStore);

        $response = $this->getJson(uri: 'http://admin.panda-estampados.test/api/v1/admin/orders/recent');

        $response->assertOk();
        $data = $response->json(key: 'data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame($orderMine->id, $data[0]['id']);
    }

    public function test_respects_limit_query_param(): void
    {
        $customer = $this->makeCustomer(stores: [$this->store]);

        // 3 orders creadas en orden cronologico (older, middle, newest)
        $older = $this->makeOrder(customer: $customer, store: $this->store, orderedAt: now()->subDays(3));
        $middle = $this->makeOrder(customer: $customer, store: $this->store, orderedAt: now()->subDays(2));
        $newest = $this->makeOrder(customer: $customer, store: $this->store, orderedAt: now()->subDay());

        $response = $this->getJson(uri: 'http://admin.panda-estampados.test/api/v1/admin/orders/recent?limit=2');

        $response->assertOk();
        $data = $response->json(key: 'data');
        $this->assertCount(2, $data);
        // orden descendente por ordered_at: newest primero
        $this->assertSame($newest->id, $data[0]['id']);
        $this->assertSame($middle->id, $data[1]['id']);
    }

    public function test_shape_has_required_keys(): void
    {
        $customer = $this->makeCustomer(stores: [$this->store]);
        $order = $this->makeOrder(customer: $customer, store: $this->store);

        $response = $this->getJson(uri: 'http://admin.panda-estampados.test/api/v1/admin/orders/recent');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'customer_name',
                    'product_summary',
                    'status_label',
                    'status_color',
                    'amount_label',
                ],
            ],
        ]);

        $row = $response->json(key: 'data.0');
        $this->assertSame('#ORD-'.$order->id, $row['order_number']);
        $this->assertNotEmpty($row['customer_name']);
        $this->assertNotEmpty($row['product_summary']);
    }

    public function test_requires_authentication(): void
    {
        // Reset: remove acting-as
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(uri: 'http://admin.panda-estampados.test/api/v1/admin/orders/recent');

        $response->assertStatus(401);
    }

    private function makeCustomer(array $stores): Customer
    {
        // Customer factory tiene columns correctas; user_id no es
        // obligatorio en factory, lo dejamos null.
        $customer = Customer::factory()->create([
            'user_id' => null,
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
        ]);
        foreach ($stores as $store) {
            $customer->stores()->attach($store->id);
        }

        return $customer;
    }

    private function makeOrder(Customer $customer, Store $store, $orderedAt = null): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'currency_id' => $store->currency_id,
            'subtotal_cents' => 4500,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 4500,
            'total_in_base_currency_cents' => 4500,
            'status' => 'pending',
            'ordered_at' => $orderedAt ?? now(),
            'order_number' => '#ORD-'.fake()->unique()->numberBetween(1000, 999999),
        ]);
    }
}
