<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShippingCompany;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del endpoint público de tracking (RF-SHIP-083).
 *
 * Cubre:
 *  - 200 con shipment + email correcto.
 *  - 404 si el código no existe.
 *  - 404 si el email no coincide (no leakeamos existencia).
 *  - 404 cross-tenant (código existe pero en otra tienda).
 *  - 422 si email no es válido.
 *  - status_label legible en español.
 */
#[Group('shipping')]
#[Group('public')]
final class TrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Store $storeA;

    private Store $storeB;

    private Customer $customer;

    private Order $order;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->primary()->create();

        $this->storeA = Store::factory()->for($currency, 'currency')->create(['slug' => 'panda-estampados']);
        StoreDomain::query()->create([
            'store_id' => $this->storeA->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->storeB = Store::factory()->for($currency, 'currency')->create(['slug' => 'kitsune']);
        StoreDomain::query()->create([
            'store_id' => $this->storeB->id,
            'domain' => 'kitsune.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'email' => 'comprador@example.com',
        ]);

        $product = Product::factory()->for($currency, 'currency')->create();

        $this->order = Order::query()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeA->id,
            'currency_id' => $currency->id,
            'order_number' => 'ORD-TEST-001',
            'subtotal_cents' => 25_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 25_00,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 25_00,
            'status' => OrderStatus::Processing,
            'ordered_at' => now(),
        ]);

        $this->order->items()->create([
            'product_id' => $product->id,
            'cart_line_key' => 'line-key-1',
            'quantity' => 1,
            'unit_price_cents' => 25_00,
            'subtotal_cents' => 25_00,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $product->sku,
        ]);

        $this->shipment = Shipment::query()->create([
            'order_id' => $this->order->id,
            'currency_id' => $currency->id,
            'tracking_code' => 'SHP-2026-ABC123',
            'shipping_cost_cents' => 0,
            'status' => ShipmentStatus::InTransit,
            'shipping_company_id' => ShippingCompany::factory()->create(['name' => 'Correos Express'])->id,
            'shipped_at' => now()->subDays(2),
        ]);

        app(StoreContextService::class)->setStore($this->storeA);
    }

    #[TestAttr]
    public function it_returns_shipment_with_correct_email(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/tracking/SHP-2026-ABC123?email=comprador@example.com');

        $response->assertOk();
        $response->assertJsonPath(path: 'data.tracking_code', expect: 'SHP-2026-ABC123');
        $response->assertJsonPath(path: 'data.status', expect: 'in_transit');
        $response->assertJsonPath(path: 'data.status_label', expect: 'En tránsito');
        $response->assertJsonPath(path: 'data.shipping_company', expect: 'Correos Express');
        $response->assertJsonPath(path: 'data.order.number', expect: 'ORD-TEST-001');
    }

    #[TestAttr]
    public function it_returns_404_for_wrong_email(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/tracking/SHP-2026-ABC123?email=otro@example.com');

        $response->assertStatus(status: 404);
        $response->assertJsonPath(path: 'message', expect: 'No encontramos un envío con ese código.');
    }

    #[TestAttr]
    public function it_returns_404_for_unknown_tracking_code(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/tracking/UNKNOWN-CODE?email=comprador@example.com');

        $response->assertStatus(status: 404);
    }

    #[TestAttr]
    public function it_returns_404_for_cross_tenant_tracking_code(): void
    {
        // Mover el shipment a storeB (otra tienda).
        $otherOrder = Order::query()->create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->storeB->id,
            'currency_id' => $this->storeA->currency_id,
            'order_number' => 'ORD-OTHER-001',
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 0,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 0,
            'status' => OrderStatus::Processing,
            'ordered_at' => now(),
        ]);
        Shipment::query()->create([
            'order_id' => $otherOrder->id,
            'currency_id' => $this->storeA->currency_id,
            'tracking_code' => 'SHP-OTHER-XYZ',
            'shipping_cost_cents' => 0,
            'status' => ShipmentStatus::Pending,
        ]);

        // Estamos en storeA — el código de storeB NO debe matchear.
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/tracking/SHP-OTHER-XYZ?email=comprador@example.com');

        $response->assertStatus(status: 404);
    }

    #[TestAttr]
    public function it_validates_email_format(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/tracking/SHP-2026-ABC123?email=not-an-email');

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['email']);
    }

    #[TestAttr]
    public function it_requires_email(): void
    {
        $response = $this->getJson(uri: 'http://panda-estampados.com/api/v1/tracking/SHP-2026-ABC123');

        $response->assertStatus(status: 422);
        $response->assertJsonValidationErrors(errors: ['email']);
    }
}
