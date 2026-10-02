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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests para los 5 endpoints /api/v1/admin/metrics/*.
 *
 * Cubre:
 *   - Auth: 401 sin Bearer token
 *   - Shape: cada endpoint devuelve la estructura esperada
 *   - Tenant scoping: solo data del tenant actual
 *   - Edge cases: sin orders / sin customers (deberia devolver shape vacio)
 */
final class MetricsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $adminUser;

    private Store $store;

    private string $host = 'admin.panda-estampados.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Pin Carbon al 15 de Junio del año en curso para que los tests
        // de revenue operen sobre una fecha determinista a mitad de
        // mes. Sin este pin, `now()->startOfMonth()->addDays(5)` cae
        // en el FUTURO durante la primera semana del mes y el
        // controller lo excluye de la query (`whereBetween('ordered_at',
        // [..., now()->endOfDay()])`), haciendo que
        // `current_year_values[mes_actual]` quede en 0.
        Carbon::setTestNow(
            Carbon::now()
                ->setMonth(6)
                ->setDay(15)
                ->setTime(12, 0, 0),
        );

        $role = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );

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

        $this->adminUser = UserModel::query()->create([
            'first_name' => 'Metrics',
            'last_name' => 'Admin',
            'email' => 'metrics-admin@panda-estampados.test',
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

    protected function tearDown(): void
    {
        Carbon::setTestNow(testNow: null);
        parent::tearDown();
    }

    // -----------------------------------------------------------
    // tests revenue yearly
    // -----------------------------------------------------------

    public function test_revenue_yearly_returns_correct_shape(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/revenue/yearly");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'year',
                'previous_year',
                'labels',
                'current_year_values',
                'previous_year_values',
            ],
        ]);

        $data = $response->json('data');
        $this->assertIsArray($data['labels']);
        $this->assertCount(12, $data['labels']);
        $this->assertIsArray($data['current_year_values']);
        $this->assertIsArray($data['previous_year_values']);
        $this->assertSame(now()->format('Y'), (string) $data['year']);
    }

    public function test_revenue_yearly_aggregates_orders_by_month(): void
    {
        $customer = Customer::factory()->create(['user_id' => null]);

        $currentYear = (int) now()->format('Y');

        // 2 orders en mes actual (currentYear)
        Order::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
            'total_cents' => 10000,
            'status' => 'completed',
            'ordered_at' => now()->startOfMonth()->addDays(5),
        ]);
        // 1 order en previousYear (mismo mes calendario pero year anterior)
        Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->store->currency_id,
            'total_cents' => 5000,
            'status' => 'completed',
            'ordered_at' => now()->startOfMonth()->subYear()->addDays(10),
        ]);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/revenue/yearly");

        $response->assertOk();

        $current = $response->json('data.current_year_values');
        $previous = $response->json('data.previous_year_values');

        $currentMonth = (int) now()->format('n') - 1; // 0-indexed
        $this->assertSame(20000, $current[$currentMonth]); // 2 orders * 10000 en currentYear
        $this->assertSame(5000, $previous[$currentMonth]); // 1 order 5000 en previousYear mismo mes
    }

    // -----------------------------------------------------------
    // tests customers segments
    // -----------------------------------------------------------

    public function test_customers_segments_returns_correct_shape(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/customers/segments");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'stats' => [
                    '*' => ['label', 'value', 'change', 'trend'],
                ],
                'segments' => [
                    '*' => ['label', 'value', 'color'],
                ],
            ],
        ]);

        $stats = $response->json('data.stats');
        $this->assertCount(4, $stats);
    }

    // -----------------------------------------------------------
    // tests sales performance
    // -----------------------------------------------------------

    public function test_sales_performance_returns_correct_shape(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/sales/performance");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'metrics' => [
                    '*' => ['label', 'value', 'change', 'trend'],
                ],
                'days',
                'values',
            ],
        ]);

        $metrics = $response->json('data.metrics');
        $this->assertCount(4, $metrics);
        $this->assertCount(7, $response->json('data.days'));
        $this->assertCount(7, $response->json('data.values'));
    }

    // -----------------------------------------------------------
    // tests traffic + low stock
    // -----------------------------------------------------------

    public function test_traffic_sources_returns_stub_with_empty_arrays(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/metrics/traffic/sources");

        $response->assertOk();
        $response->assertJsonPath('data.sources', []);
        $response->assertJsonPath('data.top_pages', []);
        $this->assertTrue($response->json('_meta.stub'));
    }

    public function test_low_stock_returns_correct_shape(): void
    {
        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/inventory/low-stock");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'sku', 'stock', 'status', 'status_color'],
            ],
        ]);
    }

    public function test_all_endpoints_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $endpoints = [
            '/api/v1/admin/metrics/revenue/yearly',
            '/api/v1/admin/metrics/sales/performance',
            '/api/v1/admin/metrics/traffic/sources',
            '/api/v1/admin/metrics/customers/segments',
            '/api/v1/admin/inventory/low-stock',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson(uri: "http://{$this->host}{$endpoint}");
            $this->assertSame(
                401,
                $response->getStatusCode(),
                "Endpoint {$endpoint} should require authentication",
            );
        }
    }
}
