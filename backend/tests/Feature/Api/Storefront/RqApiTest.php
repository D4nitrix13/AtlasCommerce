<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Storefront;

use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Models\Storefront\RfqRequest;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del endpoint público `POST /api/v1/storefront/rfq`.
 */
final class RqApiTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private string $host = 'rfq.panda-estampados.test';

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    #[TestAttr]
    public function it_creates_rfq_with_public_id_in_r_f_q_timestamp_36_format(): void
    {
        $payload = [
            'contact_name' => 'Comprador B2B',
            'contact_email' => 'buyer@cliente-nicaragua.test',
            'contact_phone' => '+505 8888 9999',
            'company_name' => 'Importadora ACME S.A.',
            'subject' => 'Cotización para 500 camisetas corporativas',
            'message' => 'Necesitamos 500 camisetas con estampado corporativo para evento en Managua.',
            'estimated_total_cents' => 2500000,
            'currency_code' => 'NIO',
        ];

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/storefront/rfq", data: $payload);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['public_id', 'status', 'contact_email', 'company_name'],
        ]);

        $publicId = $response->json('data.public_id');
        $this->assertNotEmpty($publicId);
        $this->assertStringStartsWith('RFQ-', $publicId);
        $this->assertMatchesRegularExpression('/^RFQ-[A-Z0-9]+$/', $publicId);

        $this->assertDatabaseHas(table: 'rfq_requests', data: [
            'contact_email' => 'buyer@cliente-nicaragua.test',
            'company_name' => 'Importadora ACME S.A.',
        ]);
    }

    #[TestAttr]
    public function it_links_existing_customer_by_email(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'existing@customer.test',
        ]);

        $payload = [
            'contact_name' => 'Cliente Existente',
            'contact_email' => 'existing@customer.test',
            'subject' => 'Cotización mayorista',
            'message' => 'Volumen alto para distribuir en nuestra cadena.',
        ];

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/storefront/rfq", data: $payload);

        $response->assertCreated();

        $rfq = RfqRequest::query()->where(column: 'public_id', operator: '=', value: $response->json('data.public_id'))->firstOrFail();
        $this->assertSame(expected: $customer->id, actual: $rfq->customer_id);
        $this->assertSame(expected: $this->store->id, actual: $rfq->store_id);
    }

    #[TestAttr]
    public function it_persists_items_array_when_provided(): void
    {
        $product = Product::factory()->create();

        $payload = [
            'contact_name' => 'Tester',
            'contact_email' => 'tester@test.test',
            'subject' => 'Cotización con SKUs',
            'message' => 'Necesitamos precios mayoristas.',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'notes' => 'estampado corporativo'],
            ],
        ];

        $response = $this->postJson(uri: "http://{$this->host}/api/v1/storefront/rfq", data: $payload);

        $response->assertCreated();
        $this->assertDatabaseHas(table: 'rfq_requests', data: [
            'contact_email' => 'tester@test.test',
        ]);
    }

    #[TestAttr]
    public function validation_requires_contact_fields(): void
    {
        $response = $this->postJson(uri: "http://{$this->host}/api/v1/storefront/rfq", data: []);

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(['contact_name', 'contact_email', 'subject', 'message']);
    }

    #[TestAttr]
    public function validation_rejects_invalid_email(): void
    {
        $response = $this->postJson(
            uri: "http://{$this->host}/api/v1/storefront/rfq",
            data: [
                'contact_name' => 'Tester',
                'contact_email' => 'not-an-email',
                'subject' => 'Hola',
                'message' => 'Mensaje de prueba con suficiente longitud.',
            ],
        );

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(['contact_email']);
    }
}
