<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests de los endpoints de tickets del cliente (RF-SUP-160).
 *
 * Cubre:
 *  - 401 sin token.
 *  - Listado sólo propios.
 *  - Store crea ticket + primer mensaje en una sola TX.
 *  - Show propio.
 *  - Reply agrega mensaje.
 *  - Cross-customer → 404.
 *  - User sin perfil Customer → 404.
 */
#[Group('support')]
final class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->for(
            Currency::factory()->primary()->create(),
            'currency',
        )->create(['slug' => 'panda']);
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $this->customer->stores()->attach($this->store->id);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[TestAttr]
    public function it_requires_authentication(): void
    {
        $this->getJson(uri: 'http://panda-estampados.com/api/support/tickets')
            ->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_lists_only_own_tickets(): void
    {
        $otherCustomer = Customer::factory()->create();
        SupportTicket::factory()->create(['customer_id' => $otherCustomer->id, 'store_id' => $this->store->id]);
        SupportTicket::factory()->create(['customer_id' => $this->customer->id, 'store_id' => $this->store->id]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/support/tickets');

        $response->assertOk();
        $response->assertJsonPath(path: 'meta.total', expect: 1);
    }

    #[TestAttr]
    public function it_creates_ticket_with_first_message(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/support/tickets',
            data: [
                'subject' => 'No me llegó el pedido',
                'description' => 'Pedí hace 5 días y nada.',
                'priority' => 'high',
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.status', expect: 'open');
        $response->assertJsonPath(path: 'data.priority', expect: 'high');
        $response->assertJsonPath(path: 'data.customer_id', expect: $this->customer->id);
        $response->assertJsonCount(count: 1, key: 'data.messages');
    }

    #[TestAttr]
    public function it_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/support/tickets',
            data: ['subject' => 'x'], // muy corto y sin description
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['subject', 'description']);
    }

    #[TestAttr]
    public function it_shows_own_ticket(): void
    {
        $ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);
        SupportTicketMessage::factory()->create(['support_ticket_id' => $ticket->id]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: "http://panda-estampados.com/api/support/tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonCount(count: 1, key: 'data.messages');
    }

    #[TestAttr]
    public function it_replies_to_own_ticket(): void
    {
        $ticket = SupportTicket::factory()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);
        SupportTicketMessage::factory()->create(['support_ticket_id' => $ticket->id]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/support/tickets/{$ticket->id}/reply",
            data: ['message' => 'Alguna novedad?'],
        );

        $response->assertCreated();
        $this->assertSame(2, $ticket->fresh()->messages()->count());
    }

    #[TestAttr]
    public function it_returns_404_for_other_customers_ticket(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherTicket = SupportTicket::factory()->create([
            'customer_id' => $otherCustomer->id,
            'store_id' => $this->store->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson(uri: "http://panda-estampados.com/api/support/tickets/{$otherTicket->id}")
            ->assertStatus(status: 404);

        $this->postJson(
            uri: "http://panda-estampados.com/api/support/tickets/{$otherTicket->id}/reply",
            data: ['message' => 'hack'],
        )->assertStatus(status: 404);
    }

    #[TestAttr]
    public function it_returns_404_for_user_without_customer_profile(): void
    {
        $userWithoutCustomer = User::factory()->create();
        Sanctum::actingAs($userWithoutCustomer);

        $this->getJson(uri: 'http://panda-estampados.com/api/support/tickets')
            ->assertStatus(status: 404);
    }
}
