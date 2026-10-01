<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin\Support;

use App\Models\Auth\Role;
use App\Models\Auth\User as UserModel;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\SupportTicket;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del controller admin `Api\Admin\Support\TicketAdminController`.
 */
final class TicketAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $user;

    private Store $store;

    private Customer $customer;

    private string $host = 'admin-tickets.test';

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
            'email' => 'tickets@panda-estampados.test',
            'email_verified_at' => now(),
            'password' => 'secret',
            'is_active' => true,
            'registered_at' => now(),
            'role_id' => $role->id,
        ]);
        $this->user->stores()->attach($this->store->id);

        $this->customer = Customer::factory()->create();
        $this->customer->stores()->attach($this->store->id);
    }

    #[TestAttr]
    public function index_returns_only_tickets_of_accessible_store(): void
    {
        $otherStore = Store::factory()->for($this->store->currency, 'currency')->create();
        $otherCustomer = Customer::factory()->create();

        SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);
        SupportTicket::factory()->create([
            'customer_id' => $otherCustomer->id,
            'store_id' => $otherStore->id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/support/tickets");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[TestAttr]
    public function reply_creates_message_and_auto_transitions_to_in_progress(): void
    {
        $ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/admin/support/tickets/{$ticket->id}/reply",
            data: ['message' => 'Estamos revisando tu pedido.'],
        );

        $response->assertCreated();
        $this->assertSame(expected: 'in_progress', actual: $ticket->fresh()->status);
        $this->assertSame(expected: 1, actual: $ticket->messages()->count());
    }

    #[TestAttr]
    public function reply_on_closed_ticket_returns_422(): void
    {
        $ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'closed',
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/admin/support/tickets/{$ticket->id}/reply",
            data: ['message' => 'No debería poder responder.'],
        );

        $response->assertStatus(status: 422);
    }

    #[TestAttr]
    public function close_marks_ticket_as_closed(): void
    {
        $ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'in_progress',
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/admin/support/tickets/{$ticket->id}/close");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.status', expect: 'closed');
    }

    #[TestAttr]
    public function update_modifies_status_and_priority(): void
    {
        $ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://{$this->host}/api/v1/admin/support/tickets/{$ticket->id}",
            data: [
                'status' => 'in_progress',
                'priority' => 'high',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.status', expect: 'in_progress');
        $response->assertJsonPath(path: 'data.priority', expect: 'high');
    }

    #[TestAttr]
    public function ticket_from_other_tenant_returns_404(): void
    {
        $otherStore = Store::factory()->for($this->store->currency, 'currency')->create();
        $otherTicket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $otherStore->id,
        ]);

        Sanctum::actingAs(user: $this->user, abilities: ['*']);

        $response = $this->getJson(uri: "http://{$this->host}/api/v1/admin/support/tickets/{$otherTicket->id}");

        $response->assertStatus(status: 404);
    }
}
