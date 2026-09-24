<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use App\Enums\ShipmentStatus;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use App\Models\Payments\PaymentMethod;
use App\Models\Returns\Refund;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Payments\PaymentService;
use App\Services\Returns\RefundService;
use App\Services\Returns\ReturnRequestService;
use App\Services\Shipping\ShipmentService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('http')]
#[Group('resources')]
final class ResourceShapeHttpTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    private Order $order;

    private Invoice $invoice;

    private ShippingZone $zone;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        app(StoreContextService::class)->clear();

        $this->currency = Currency::factory()->primary()->create(attributes: [
            'code' => 'USD',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);
        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create();
        // Crea el rol admin (bypasea chequeos de permisos) y asigna al user de test.
        $adminRole = Role::query()->updateOrCreate(
            attributes: ['slug' => 'admin'],
            values: ['name' => 'Administrador', 'is_active' => true],
        );
        $this->user = User::factory()->create(attributes: [
            'password' => Hash::make(value: 'secret'),
            'role_id' => $adminRole->id,
        ]);
        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->user->id]);
        $this->warehouse = Warehouse::factory()->create(attributes: ['is_active' => true]);
        $this->zone = ShippingZone::factory()->forStore($this->store)->create(attributes: [
            'base_cost_cents' => 5_00,
            'is_active' => true,
        ]);
        $this->paymentMethod = PaymentMethod::factory()->create();

        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['base_price_cents' => 25_00]);

        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->seedOrderAndInvoice();

        app(StoreContextService::class)->setStore($this->store);

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function shipment_resource_returns_expected_shape_with_money_formatter(): void
    {
        $payment = app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();

        $response = $this->getJson("/api/v1/shipments/{$shipment->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $shipment->id);
        $response->assertJsonPath('data.status', ShipmentStatus::Pending->value);
        $response->assertJsonPath('data.tracking_code', $shipment->tracking_code);

        // Money formatter shape: { cents, formatted, currency_code }
        $response->assertJsonStructure([
            'data' => [
                'shipping_cost' => ['cents', 'formatted', 'currency_code'],
            ],
        ]);
        $response->assertJsonPath('data.shipping_cost.cents', 5_00);
        $response->assertJsonPath('data.shipping_cost.currency_code', 'USD');

        // Relations cargadas con whenLoaded.
        $response->assertJsonPath('data.currency.code', 'USD');
        $response->assertJsonPath('data.shipping_zone.name', $this->zone->name);
    }

    #[Test]
    public function shipment_transition_endpoint_returns_resource(): void
    {
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();

        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/ship");

        $response->assertOk();
        $response->assertJsonPath('data.status', ShipmentStatus::Shipped->value);
        $response->assertJsonStructure(['data' => ['shipped_at']]);
    }

    #[Test]
    public function shipment_transition_endpoint_returns_422_on_invalid_transition(): void
    {
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()->where(column: 'order_id', operator: '=', value: $this->order->id)->firstOrFail();
        app(ShipmentService::class)->markAsShipped(shipment: $shipment);

        // pending → cancelled ya no es válido después de ship.
        $response = $this->postJson("/api/v1/shipments/{$shipment->id}/cancel");
        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    #[Test]
    public function return_request_resource_returns_expected_shape(): void
    {
        $returnRequest = app(ReturnRequestService::class)->create(
            order: $this->order,
            reason: 'Test reason',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        app(ReturnRequestService::class)->approve(returnRequest: $returnRequest);

        $response = $this->getJson("/api/v1/return-requests/{$returnRequest->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $returnRequest->id);
        $response->assertJsonPath('data.status', ReturnRequestStatus::Approved->value);
        $response->assertJsonPath('data.reason', 'Test reason');

        $response->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => ['id', 'product_id', 'quantity', 'amount' => ['cents', 'formatted', 'currency_code']],
                ],
                'order' => ['id', 'order_number'],
                'customer' => ['id'],
            ],
        ]);

        $response->assertJsonPath('data.items.0.quantity', 1);
        $response->assertJsonPath('data.items.0.amount.cents', 25_00);
    }

    #[Test]
    public function return_request_transition_endpoint_works(): void
    {
        $returnRequest = app(ReturnRequestService::class)->create(
            order: $this->order,
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );

        $response = $this->postJson("/api/v1/return-requests/{$returnRequest->id}/approve");
        $response->assertOk();
        $response->assertJsonPath('data.status', ReturnRequestStatus::Approved->value);
    }

    #[Test]
    public function refund_resource_returns_expected_shape_with_money_formatter(): void
    {
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = app(ReturnRequestService::class)->create(
            order: $this->order->fresh(),
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        app(ReturnRequestService::class)->approve(returnRequest: $returnRequest);

        $refund = app(RefundService::class)->create(
            returnRequest: $returnRequest->fresh(),
            amountCents: 25_00,
        );

        $response = $this->getJson("/api/v1/refunds/{$refund->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $refund->id);
        $response->assertJsonPath('data.status', RefundStatus::Pending->value);
        $response->assertJsonStructure([
            'data' => [
                'amount' => ['cents', 'formatted', 'currency_code'],
                'return_request' => ['id', 'status'],
            ],
        ]);
        $response->assertJsonPath('data.amount.cents', 25_00);
        $response->assertJsonPath('data.amount.currency_code', 'USD');
        $response->assertJsonPath('data.return_request.status', ReturnRequestStatus::Approved->value);
    }

    #[Test]
    public function refund_process_endpoint_completes_and_returns_resource(): void
    {
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = app(ReturnRequestService::class)->create(
            order: $this->order->fresh(),
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        app(ReturnRequestService::class)->approve(returnRequest: $returnRequest);

        $response = $this->postJson(uri: '/api/v1/refunds', data: [
            'return_request_id' => $returnRequest->id,
            'amount_cents' => 25_00,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', RefundStatus::Pending->value);

        $refundId = $response->json('data.id');

        $processResponse = $this->postJson("/api/v1/refunds/{$refundId}/process");
        $processResponse->assertOk();
        $processResponse->assertJsonPath('data.status', RefundStatus::Completed->value);
        $processResponse->assertJsonStructure(['data' => ['refunded_at']]);
    }

    #[Test]
    public function refund_process_endpoint_returns_422_on_overpayment(): void
    {
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = app(ReturnRequestService::class)->create(
            order: $this->order->fresh(),
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        app(ReturnRequestService::class)->approve(returnRequest: $returnRequest);

        $refund = app(RefundService::class)->create(
            returnRequest: $returnRequest->fresh(),
            amountCents: 25_00,
        );

        // Forzar overpayment via factory directo (bypass del service).
        $overRefund = Refund::create([
            'store_id' => $this->store->id,
            'return_request_id' => $returnRequest->id,
            'payment_method_id' => null,
            'currency_id' => $this->currency->id,
            'amount_cents' => 99_99, // > 25_00 (refundable)
            'exchange_rate_snapshot' => 1.0,
            'status' => RefundStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/refunds/{$overRefund->id}/process");
        $response->assertStatus(422);
    }

    #[Test]
    public function shipment_index_endpoint_returns_paginated_resource_collection(): void
    {
        app(PaymentService::class)->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $response = $this->getJson('/api/v1/shipments');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_id',
                    'status',
                    'tracking_code',
                    'shipping_cost' => ['cents', 'formatted', 'currency_code'],
                ],
            ],
        ]);
    }

    private function seedOrderAndInvoice(): void
    {
        $address = CustomerAddress::factory()->for($this->customer)->create();

        $this->order = Order::create([
            'customer_id' => $this->customer->id,
            'shipping_address_id' => $address->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'shipping_zone_id' => $this->zone->id,
            'total_cents' => 55_00,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 55_00,
            'status' => 'pending',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price_cents' => 25_00,
            'discount_cents' => 0,
            'subtotal_cents' => 50_00,
            'product_name_snapshot' => $this->product->name,
            'product_sku_snapshot' => $this->product->sku,
        ]);

        $this->invoice = Invoice::create([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 5_00,
            'shipping_zone_id' => $this->zone->id,
            'total_cents' => 55_00,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price_cents' => 25_00,
            'subtotal_cents' => 50_00,
            'product_name_snapshot' => $this->product->name,
            'product_sku_snapshot' => $this->product->sku,
        ]);
    }
}
