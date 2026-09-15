<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD admin de tickets de soporte (RF-SUP-160).
 *
 * Cubre:
 *  - 401 sin token, 403 sin permiso `support.manage`/`support.view`.
 *  - Listado paginado + filtros (status, priority) con tenant isolation.
 *  - Show con messages cargados.
 *  - Update status/priority.
 *  - Reply crea mensaje + auto-transiciona open → in_progress.
 *  - Close marca `closed` (terminal).
 *  - No acepta replies en tickets cerrados.
 *  - Cross-tenant → 404.
 */
#[Group('admin')]
#[Group('support')]
final class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    private Customer $customer;

    private SupportTicket $ticket;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);
        $adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();

        $currency = Currency::factory()->primary()->create();

        $this->store = Store::factory()->for($currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@panda-estampados.com',
            'is_active' => true,
            'role_id' => $adminRole->id,
        ]);
        $this->admin->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        $this->unauthorized = User::factory()->create();

        $this->customer = Customer::factory()->create();
        $this->customer->stores()->attach($this->store->id);

        $this->ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'subject' => 'Problema con mi pedido',
            'description' => 'Mi pedido no llegó',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // Primer mensaje del cliente.
        SupportTicketMessage::factory()->create([
            'support_ticket_id' => $this->ticket->id,
            'user_id' => null,
            'message' => 'Mi pedido no llegó',
            'messaged_at' => now(),
        ]);
    }

    #[TestAttr]
    public function index_requires_authentication(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/support/tickets');
        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function index_denies_user_without_support_view(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/support/tickets');
        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function index_returns_only_accessible_tickets(): void
    {
        $otherStore = Store::factory()->for($this->store->currency, 'currency')->create();
        $otherCustomer = Customer::factory()->create();
        SupportTicket::factory()->create([
            'customer_id' => $otherCustomer->id,
            'store_id' => $otherStore->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/admin/support/tickets');
        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[TestAttr]
    public function index_filters_by_status_and_priority(): void
    {
        SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'status' => 'closed',
            'priority' => 'urgent',
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $openOnly = $this->getJson(uri: 'http://panda-estampados.com/api/admin/support/tickets?status=open');
        $openOnly->assertJsonPath(path: 'meta.total', expect: 1);

        $urgent = $this->getJson(uri: 'http://panda-estampados.com/api/admin/support/tickets?priority=urgent');
        $urgent->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[TestAttr]
    public function show_returns_ticket_with_messages(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/support/tickets/{$this->ticket->id}");

        $response->assertOk();
        $response->assertJsonPath(path: 'data.id', expect: $this->ticket->id);
        $response->assertJsonCount(count: 1, key: 'data.messages');
    }

    #[TestAttr]
    public function update_status_and_priority(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/admin/support/tickets/{$this->ticket->id}",
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
    public function reply_creates_message_and_auto_transitions_status(): void
    {
        $this->assertSame('open', $this->ticket->status);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/support/tickets/{$this->ticket->id}/reply",
            data: ['message' => 'Estamos revisando tu pedido.'],
        );

        $response->assertCreated();
        $this->assertSame('in_progress', $this->ticket->fresh()->status);
        $this->assertSame(2, $this->ticket->messages()->count());
    }

    #[TestAttr]
    public function reply_on_closed_ticket_returns_422(): void
    {
        $this->ticket->update(['status' => 'closed']);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/support/tickets/{$this->ticket->id}/reply",
            data: ['message' => 'Hola?'],
        );

        $response->assertStatus(status: 422);
    }

    #[TestAttr]
    public function close_marks_ticket_as_closed(): void
    {
        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/admin/support/tickets/{$this->ticket->id}/close",
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.status', expect: 'closed');
    }

    #[TestAttr]
    public function ticket_from_other_tenant_returns_404(): void
    {
        $otherStore = Store::factory()->for($this->store->currency, 'currency')->create();
        $otherTicket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $otherStore->id,
        ]);

        Sanctum::actingAs(user: $this->admin, abilities: ['*']);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/admin/support/tickets/{$otherTicket->id}");

        $response->assertStatus(status: 404);
    }
}
