<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del CRUD del libro de direcciones del cliente (RF-CUST-041).
 *
 * Cubre:
 *  - 401 sin token.
 *  - Listado paginado-like (todas las del cliente autenticado).
 *  - Store + validación (address_type enum).
 *  - Show / Update / Destroy.
 *  - Set as primary desmarca las demás del mismo cliente (transaccional).
 *  - Cross-customer: no podés ver/editar direcciones de otro cliente (404).
 */
#[Group('customers')]
final class CustomerAddressControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private CustomerAddress $address;

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
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'address_type' => 'shipping',
            'is_primary' => true,
        ]);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[TestAttr]
    public function it_requires_authentication(): void
    {
        $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/addresses')
            ->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_lists_only_own_addresses(): void
    {
        $otherCustomer = Customer::factory()->create();
        CustomerAddress::factory()->create([
            'customer_id' => $otherCustomer->id,
            'is_primary' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/addresses');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[TestAttr]
    public function it_stores_address(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/account/addresses',
            data: [
                'address_type' => 'billing',
                'country' => 'Nicaragua',
                'department' => 'Managua',
                'municipality' => 'Managua',
                'detailed_address' => 'Av. Principal 123',
                'reference' => 'Casa amarilla',
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath(path: 'data.address_type', expect: 'billing');
        $response->assertJsonPath(path: 'data.country', expect: 'Nicaragua');
        $response->assertJsonPath(path: 'data.customer_id', expect: $this->customer->id);
    }

    #[TestAttr]
    public function it_validates_address_type(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/account/addresses',
            data: [
                'address_type' => 'invalid',
                'country' => 'Nicaragua',
                'detailed_address' => 'Calle 123',
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['address_type']);
    }

    #[TestAttr]
    public function it_shows_address(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$this->address->id}",
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.id', expect: $this->address->id);
    }

    #[TestAttr]
    public function it_updates_partial_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$this->address->id}",
            data: [
                'department' => 'León',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.department', expect: 'León');
        // Otros campos sin cambios.
        $response->assertJsonPath(path: 'data.country', expect: $this->address->country);
    }

    #[TestAttr]
    public function it_set_primary_demotes_others(): void
    {
        $other = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_primary' => true,
        ]);

        Sanctum::actingAs($this->user);

        $this->assertTrue((bool) $other->is_primary);

        $response = $this->postJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$this->address->id}/primary",
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.is_primary', expect: true);

        $this->assertFalse((bool) $other->fresh()->is_primary);
        $this->assertTrue((bool) $this->address->fresh()->is_primary);
    }

    #[TestAttr]
    public function it_create_with_is_primary_demotes_existing(): void
    {
        $existing = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_primary' => true,
        ]);

        Sanctum::actingAs($this->user);

        $this->postJson(
            uri: 'http://panda-estampados.com/api/v1/account/addresses',
            data: [
                'address_type' => 'shipping',
                'country' => 'Nicaragua',
                'detailed_address' => 'Otra calle 456',
                'is_primary' => true,
            ],
        )->assertCreated();

        $this->assertFalse((bool) $existing->fresh()->is_primary);
    }

    #[TestAttr]
    public function it_destroys_address(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$this->address->id}",
        )->assertNoContent();

        $this->assertSoftDeleted(table: 'customer_addresses', data: ['id' => $this->address->id]);
    }

    #[TestAttr]
    public function it_returns_404_for_other_customers_address(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherAddress = CustomerAddress::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$otherAddress->id}",
        )->assertStatus(status: 404);

        $this->putJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$otherAddress->id}",
            data: ['department' => 'Hack'],
        )->assertStatus(status: 404);

        $this->postJson(
            uri: "http://panda-estampados.com/api/v1/account/addresses/{$otherAddress->id}/primary",
        )->assertStatus(status: 404);
    }

    #[TestAttr]
    public function it_returns_404_for_user_without_customer_profile(): void
    {
        $userWithoutCustomer = User::factory()->create();
        Sanctum::actingAs($userWithoutCustomer);

        $this->getJson(uri: 'http://panda-estampados.com/api/v1/account/addresses')
            ->assertStatus(status: 404);
    }
}
