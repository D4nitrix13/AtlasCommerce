<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
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
 * Tests del CRUD admin de clientes (RF-CUST-040).
 *
 * Cubre:
 *  - Auth: 401 sin token, 403 sin `customers.view`/`customers.write`.
 *  - Listado paginado + filtros (q, customer_type, is_active, store_id, with_trashed).
 *  - Aislamiento por tenant: no ver clientes de otras tiendas.
 *  - Show con order_count + lifetime_value_cents.
 *  - Store con validación + aislamiento por tienda.
 *  - Update parcial.
 *  - Suspend / Reactivate.
 *  - Destroy (soft-delete).
 */
#[Group('admin')]
#[Group('customers')]
final class CustomerControllerTest extends TestCase
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
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_customers_view_permission(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_returns_only_customers_in_tenant(): void
    {
        $this->makeCustomer(email: 'a@example.com', storeId: $this->storeA->id);
        $this->makeCustomer(email: 'b@example.com', storeId: $this->storeA->id);
        $this->makeCustomer(email: 'c@example.com', storeId: $this->storeB->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 2);
        $emails = collect(value: $response->json('data'))->pluck('email')->all();
        $this->assertContains(needle: 'a@example.com', haystack: $emails);
        $this->assertContains(needle: 'b@example.com', haystack: $emails);
        $this->assertNotContains(needle: 'c@example.com', haystack: $emails);
    }

    #[TestAttr]
    public function index_filters_by_q_email_and_customer_type(): void
    {
        $this->makeCustomer(email: 'maria@example.com', firstName: 'Maria', customerType: 'retail', storeId: $this->storeA->id);
        $this->makeCustomer(email: 'mayorista@example.com', firstName: 'Carlos', customerType: 'wholesale', storeId: $this->storeA->id);
        $this->makeCustomer(email: 'vip@example.com', firstName: 'Lucia', customerType: 'vip', storeId: $this->storeA->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $byQ = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers?q=maria');
        $byQ->assertJsonPath(path: 'meta.total', expect: 1);
        $byQ->assertJsonPath(path: 'data.0.email', expect: 'maria@example.com');

        $byType = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers?customer_type=wholesale');
        $byType->assertJsonPath(path: 'meta.total', expect: 1);
        $byType->assertJsonPath(path: 'data.0.customer_type', expect: 'wholesale');
    }

    #[TestAttr]
    public function show_returns_customer_with_order_history_and_lifetime_value(): void
    {
        $customer = $this->makeCustomer(email: 'comprador@example.com', storeId: $this->storeA->id);
        $this->makeOrder(customer: $customer, totalCents: 25_00, status: OrderStatus::Completed);
        $this->makeOrder(customer: $customer, totalCents: 15_00, status: OrderStatus::Cancelled);
        $this->makeOrder(customer: $customer, totalCents: 30_00, status: OrderStatus::Processing);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/customers/{$customer->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.email', expect: 'comprador@example.com');
        $response->assertJsonPath(path: 'data.order_count', expect: 3);
        // LTV = 25_00 + 30_00 (Cancelled excluded).
        $response->assertJsonPath(path: 'data.lifetime_value_cents', expect: 55_00);
        $this->assertNull($response->json('data.deletion_token_hash'));
    }

    #[TestAttr]
    public function store_creates_customer_in_tenant(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/admin/customers',
            data: [
                'first_name' => 'Ana',
                'last_name' => 'García',
                'email' => 'ana@example.com',
                'phone' => '+54911223344',
                'customer_type' => 'wholesale',
                'identity_document' => '20123456789',
                'store_ids' => [$this->storeA->id],
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.email', expect: 'ana@example.com');
        $response->assertJsonPath(path: 'data.customer_type', expect: 'wholesale');
        $response->assertJsonPath(path: 'data.is_active', expect: true);

        $this->assertDatabaseHas(table: 'customers', data: ['email' => 'ana@example.com']);
        $this->assertDatabaseHas(table: 'customer_stores', data: ['store_id' => $this->storeA->id]);
    }

    #[TestAttr]
    public function store_rejects_store_outside_tenant_scope(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/admin/customers',
            data: [
                'first_name' => 'Otro',
                'last_name' => 'Tienda',
                'email' => 'otro@example.com',
                'customer_type' => 'retail',
                'store_ids' => [$this->storeB->id],
            ],
        );

        $response->assertStatus(status: 403);
        $this->assertDatabaseMissing(table: 'customers', data: ['email' => 'otro@example.com']);
    }

    #[TestAttr]
    public function store_validates_unique_email(): void
    {
        $this->makeCustomer(email: 'duplicado@example.com', storeId: $this->storeA->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/admin/customers',
            data: [
                'first_name' => 'Dup',
                'last_name' => 'Test',
                'email' => 'duplicado@example.com',
                'customer_type' => 'retail',
                'store_ids' => [$this->storeA->id],
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['email']);
    }

    #[TestAttr]
    public function update_partial_fields(): void
    {
        $customer = $this->makeCustomer(email: 'update@example.com', customerType: 'retail', storeId: $this->storeA->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/admin/customers/{$customer->id}",
            data: [
                'customer_type' => 'vip',
                'phone' => '+54999887766',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.customer_type', expect: 'vip');
        $response->assertJsonPath(path: 'data.phone', expect: '+54999887766');
        // Email y first_name sin cambios.
        $response->assertJsonPath(path: 'data.email', expect: 'update@example.com');
    }

    #[TestAttr]
    public function suspend_and_reactivate_toggle_is_active(): void
    {
        $customer = $this->makeCustomer(email: 'toggle@example.com', storeId: $this->storeA->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $suspend = $this->postJson(uri: "http://panda-estampados.com/api/admin/customers/{$customer->id}/suspend");
        $suspend->assertOk();
        $suspend->assertJsonPath(path: 'data.is_active', expect: false);

        $reactivate = $this->postJson(uri: "http://panda-estampados.com/api/admin/customers/{$customer->id}/reactivate");
        $reactivate->assertOk();
        $reactivate->assertJsonPath(path: 'data.is_active', expect: true);
    }

    #[TestAttr]
    public function destroy_soft_deletes_customer(): void
    {
        $customer = $this->makeCustomer(email: 'soft@example.com', storeId: $this->storeA->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $this->deleteJson(uri: "http://panda-estampados.com/api/admin/customers/{$customer->id}")
            ->assertNoContent();

        $this->assertSoftDeleted(table: 'customers', data: ['id' => $customer->id]);

        // Por defecto NO aparece en el listado (sin with_trashed).
        $list = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers');
        $list->assertJsonPath(path: 'meta.total', expect: 0);

        // Con with_trashed=1 sí aparece.
        $listAll = $this->getJson(uri: 'http://panda-estampados.com/api/admin/customers?with_trashed=1');
        $listAll->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[TestAttr]
    public function customer_from_other_tenant_returns_404(): void
    {
        $otherCustomer = $this->makeCustomer(email: 'otro-tenant@example.com', storeId: $this->storeB->id);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/customers/{$otherCustomer->id}");

        $response->assertStatus(status: 404);
    }

    private function makeCustomer(
        string $email,
        int $storeId,
        string $firstName = 'Test',
        string $customerType = 'retail',
    ): Customer {
        $customer = Customer::query()->create(attributes: [
            'first_name' => $firstName,
            'last_name' => 'Apellido',
            'email' => $email,
            'customer_type' => $customerType,
            'is_active' => true,
            'registered_at' => now(),
        ]);

        $customer->stores()->attach(ids: [$storeId]);

        return $customer;
    }

    private function makeOrder(Customer $customer, int $totalCents, OrderStatus $status): Order
    {
        return Order::query()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->storeA->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => $totalCents,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => $totalCents,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => $totalCents,
            'status' => $status,
            'ordered_at' => now(),
        ]);
    }
}
