<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Enums\ShipmentStatus;
use App\Exceptions\Shipping\InvalidShipmentTransitionException;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Payments\PaymentService;
use App\Services\Shipping\ShipmentService;
use App\Services\Stores\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('shipping')]
#[Group('payments')]
final class ShipmentOnPaymentTest extends TestCase
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

    private PaymentService $payments;

    private ShipmentService $shipments;

    protected function setUp(): void
    {
        parent::setUp();

        app(StoreContextService::class)->clear();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $address = CustomerAddress::factory()->for($this->customer)->create();
        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->zone = ShippingZone::factory()->create([
            'base_cost_cents' => 5_00,
            'is_active' => true,
        ]);
        $this->paymentMethod = PaymentMethod::factory()->create();

        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['base_price_cents' => 25_00]);

        // Stock suficiente para que el decremento no falle.
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->seedOrderAndInvoice(
            quantity: 2,
            unitPriceCents: 25_00,
            shippingCents: 5_00,
            shippingZoneId: $this->zone->id,
        );

        app(StoreContextService::class)->setStore($this->store);

        $this->payments = app(PaymentService::class);
        $this->shipments = app(ShipmentService::class);
    }

    #[Test]
    public function full_payment_creates_pending_shipment_with_tracking_code(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertNotNull($shipment->tracking_code);
        $this->assertStringStartsWith('SHP-', $shipment->tracking_code);
        $this->assertSame(5_00, $shipment->shipping_cost_cents);
        $this->assertSame($this->zone->id, $shipment->shipping_zone_id);
        $this->assertSame($this->invoice->currency_id, $shipment->currency_id);
        $this->assertNull($shipment->shipped_at);
        $this->assertNull($shipment->delivered_at);
    }

    #[Test]
    public function partial_payment_does_not_create_shipment(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: 30_00,
        );

        $this->assertSame(0, Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->count());
    }

    #[Test]
    public function shipment_creation_is_idempotent_for_repeated_settlement(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $first = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        // Llamada directa: si ya existe un shipment activo para la misma invoice, retorna el existente.
        $second = $this->shipments->createForInvoice(invoice: $this->invoice->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->count());
    }

    #[Test]
    public function shipment_can_transition_pending_to_shipped(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->markAsShipped(shipment: $shipment);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Shipped, $shipment->status);
        $this->assertNotNull($shipment->shipped_at);
    }

    #[Test]
    public function shipment_can_transition_shipped_to_delivered(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->markAsShipped(shipment: $shipment);
        $this->shipments->markAsDelivered(
            shipment: $shipment->fresh(),
            deliveredAt: Carbon::parse('2026-08-01 14:00:00'),
        );

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('2026-08-01 14:00:00', $shipment->delivered_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function shipment_can_transition_shipped_to_in_transit_to_delivered(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->markAsShipped(shipment: $shipment);
        $this->shipments->markAsInTransit(shipment: $shipment->fresh());
        $this->shipments->markAsDelivered(shipment: $shipment->fresh());

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
    }

    #[Test]
    public function shipment_can_be_cancelled_from_pending(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->cancel(shipment: $shipment);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Cancelled, $shipment->status);
    }

    #[Test]
    public function shipment_cannot_be_cancelled_after_shipping(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->markAsShipped(shipment: $shipment);

        $this->expectException(InvalidShipmentTransitionException::class);
        $this->shipments->cancel(shipment: $shipment->fresh());
    }

    #[Test]
    public function delivered_shipment_cannot_transition_anywhere(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->markAsShipped(shipment: $shipment);
        $this->shipments->markAsDelivered(shipment: $shipment->fresh());

        $delivered = $shipment->fresh();

        $this->expectException(InvalidShipmentTransitionException::class);
        $this->shipments->markAsShipped(shipment: $delivered);
    }

    #[Test]
    public function cancelled_shipment_can_be_replaced_by_a_new_one(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $first = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->shipments->cancel(shipment: $first);

        // Tras cancelar, la idempotencia NO bloquea crear uno nuevo.
        $second = $this->shipments->createForInvoice(invoice: $this->invoice->fresh());

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->count());
        $this->assertSame(ShipmentStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(ShipmentStatus::Pending, $second->status);
    }

    #[Test]
    public function shipment_status_enum_validates_transitions(): void
    {
        // Tabla de transiciones válidas.
        $this->assertTrue(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Shipped));
        $this->assertTrue(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Cancelled));
        $this->assertTrue(ShipmentStatus::Shipped->canTransitionTo(ShipmentStatus::InTransit));
        $this->assertTrue(ShipmentStatus::Shipped->canTransitionTo(ShipmentStatus::Delivered));
        $this->assertTrue(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Delivered));

        // Inválidas.
        $this->assertFalse(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Delivered));
        $this->assertFalse(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::Shipped));
        $this->assertFalse(ShipmentStatus::Cancelled->canTransitionTo(ShipmentStatus::Shipped));
        $this->assertTrue(ShipmentStatus::Delivered->isTerminal());
        $this->assertTrue(ShipmentStatus::Cancelled->isTerminal());
    }

    #[Test]
    public function invoice_without_shipping_zone_still_creates_shipment(): void
    {
        // Crear una segunda invoice SIN shipping_zone_id.
        $this->invoice->update(['shipping_zone_id' => null]);
        $this->invoice->update(['shipping_cents' => 0]);

        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice->fresh(),
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $shipment = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->firstOrFail();

        $this->assertNull($shipment->shipping_zone_id);
        $this->assertSame(0, $shipment->shipping_cost_cents);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
    }

    /**
     * Crea Order + Invoice + OrderItem + InvoiceItem con zona opcional.
     */
    private function seedOrderAndInvoice(
        int $quantity,
        int $unitPriceCents,
        int $shippingCents = 0,
        ?int $shippingZoneId = null,
    ): void {
        $this->order = Order::create([
            'customer_id' => $this->customer->id,
            'shipping_address_id' => CustomerAddress::factory()->for($this->customer)->create()->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => $unitPriceCents * $quantity,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => $shippingCents,
            'total_cents' => $unitPriceCents * $quantity + $shippingCents,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => $unitPriceCents * $quantity + $shippingCents,
            'status' => 'pending',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'discount_cents' => 0,
            'subtotal_cents' => $unitPriceCents * $quantity,
            'product_name_snapshot' => $this->product->name,
            'product_sku_snapshot' => $this->product->sku,
        ]);

        $this->invoice = Invoice::create([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal_cents' => $unitPriceCents * $quantity,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => $shippingCents,
            'shipping_zone_id' => $shippingZoneId,
            'total_cents' => $unitPriceCents * $quantity + $shippingCents,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'subtotal_cents' => $unitPriceCents * $quantity,
            'product_name_snapshot' => $this->product->name,
            'product_sku_snapshot' => $this->product->sku,
        ]);
    }
}
