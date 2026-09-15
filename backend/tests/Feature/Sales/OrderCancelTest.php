<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\Warehouse;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Sales\OrderStatusHistory;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use App\Services\Payments\PaymentService;
use Core\Tenant\Application\StoreContextService;
use Database\Seeders\PermissionsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests de cancelación hard de órdenes (RF-SALES-054).
 *
 * Cubre:
 *  - Happy path unpaid: orden sin pagar → cancelada + invoice cancelled.
 *  - Happy path paid: orden pagada → cancelada + stock revertido + refunded_cents.
 *  - Bloqueo: shipment ya despachado → 422.
 *  - Bloqueo: orden ya cancelada → 422 (idempotencia a nivel servicio).
 *  - Auth: sin token → 401; sin permiso → 403.
 *  - Unit: máquina de estados de OrderStatus.
 */
#[Group('sales')]
#[Group('inventory')]
final class OrderCancelTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    private User $admin;

    private User $staff;

    private User $unauthorized;

    private Warehouse $warehouse;

    private Product $product;

    private Order $order;

    private Invoice $invoice;

    private PaymentMethod $paymentMethod;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: PermissionsCatalogSeeder::class);

        $adminRole = Role::query()->where(column: 'slug', operator: '=', value: 'admin')->firstOrFail();
        $staffRole = Role::query()->where(column: 'slug', operator: '=', value: 'staff')->firstOrFail();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create();
        $this->admin = User::factory()->create(attributes: ['role_id' => $adminRole->id]);
        $this->staff = User::factory()->create(attributes: ['role_id' => $staffRole->id]);
        $this->unauthorized = User::factory()->create();

        $this->admin->stores()->syncWithoutDetaching(ids: [$this->store->id]);
        $this->staff->stores()->syncWithoutDetaching(ids: [$this->store->id]);

        StoreDomain::query()->create(attributes: [
            'store_id' => $this->store->id,
            'domain' => 'panda-estampados.com',
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->admin->id]);
        $this->warehouse = Warehouse::factory()->create(attributes: ['is_active' => true]);
        $this->paymentMethod = PaymentMethod::factory()->create();
        $this->payments = app(PaymentService::class);

        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(attributes: ['base_price_cents' => 25_00]);

        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->seedOrderAndInvoice(quantity: 2, unitPriceCents: 25_00);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[TestAttr]
    public function it_cancels_an_unpaid_order_marks_invoice_cancelled_and_writes_history(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->postJson(
            uri: $this->cancelUrl($this->order->id),
            data: ['observation' => 'Cliente solicitó cancelación antes del pago.'],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.status', expect: OrderStatus::Cancelled->value);

        $this->order->refresh();
        $this->invoice->refresh();

        $this->assertSame(OrderStatus::Cancelled, $this->order->status);
        $this->assertSame('cancelled', $this->invoice->invoice_status);
        $this->assertSame(0, (int) $this->invoice->refunded_cents);

        $history = OrderStatusHistory::query()
            ->where(column: 'order_id', operator: '=', value: $this->order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame(OrderStatus::Pending->value, $history->previous_status);
        $this->assertSame(OrderStatus::Cancelled->value, $history->new_status);
        $this->assertSame($this->staff->id, $history->user_id);
        $this->assertSame('Cliente solicitó cancelación antes del pago.', $history->observation);
    }

    #[TestAttr]
    public function it_cancels_a_paid_order_reverts_stock_and_reflects_refund_in_invoice(): void
    {
        // Liquidar la invoice: dispara decremento de stock 100→98.
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $this->assertSame(98, $this->currentStock());

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->postJson(uri: $this->cancelUrl($this->order->id));

        $response->assertOk();

        $this->order->refresh();
        $this->invoice->refresh();

        $this->assertSame(OrderStatus::Cancelled, $this->order->status);
        $this->assertSame('cancelled', $this->invoice->invoice_status);

        // El monto pagado (50_00) se refleja como refunded_cents — no se crea
        // un Refund explícito, queda en el historial de la orden.
        $this->assertSame(50_00, (int) $this->invoice->refunded_cents);

        // Stock revertido: 100 de vuelta.
        $this->assertSame(100, $this->currentStock());

        // InventoryMovement con prefijo de cancelación.
        $itemId = (int) $this->order->items()->first()->id;
        $this->assertDatabaseHas(table: 'inventory_movements', data: [
            'movement_type' => 'cancellation',
            'reason' => "cancellation:order:{$this->order->id}:item:{$itemId}",
        ]);
    }

    #[TestAttr]
    public function it_cannot_cancel_an_order_with_shipped_shipment(): void
    {
        Shipment::create([
            'order_id' => $this->order->id,
            'shipping_company_id' => null,
            'currency_id' => $this->currency->id,
            'tracking_code' => 'SHP-TEST-001',
            'shipping_cost_cents' => 0,
            'status' => ShipmentStatus::Shipped,
        ]);

        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $response = $this->postJson(uri: $this->cancelUrl($this->order->id));

        $response->assertStatus(status: 422);
        $response->assertJsonPath(path: 'message', expect: "La orden no puede cancelarse: tiene un envío en estado 'shipped'.");

        $this->order->refresh();
        $this->assertSame(OrderStatus::Pending, $this->order->status);
    }

    #[TestAttr]
    public function it_cannot_cancel_an_already_cancelled_order(): void
    {
        Sanctum::actingAs(user: $this->staff, abilities: ['*']);

        $this->postJson(uri: $this->cancelUrl($this->order->id))->assertOk();

        // Segundo intento: 422 por transición inválida.
        $response = $this->postJson(uri: $this->cancelUrl($this->order->id));

        $response->assertStatus(status: 422);
        $response->assertJsonPath(
            path: 'message',
            expect: 'Transición de orden no permitida: cancelled → cancelled.',
        );

        // Solo se creó UN OrderStatusHistory con la cancelación.
        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(column: 'order_id', operator: '=', value: $this->order->id)
                ->where(column: 'new_status', operator: '=', value: OrderStatus::Cancelled->value)
                ->count(),
        );
    }

    #[TestAttr]
    public function it_blocks_unauthenticated_user_with_401(): void
    {
        $response = $this->postJson(uri: $this->cancelUrl($this->order->id));

        $response->assertStatus(status: 401);
    }

    #[TestAttr]
    public function it_blocks_user_without_sales_orders_cancel_permission_with_403(): void
    {
        Sanctum::actingAs(user: $this->unauthorized, abilities: ['*']);

        $response = $this->postJson(uri: $this->cancelUrl($this->order->id));

        $response->assertStatus(status: 403);
    }

    #[TestAttr]
    public function it_validates_order_status_enum_transitions(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Processing));
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::Processing->canTransitionTo(OrderStatus::Completed));
        $this->assertTrue(OrderStatus::Processing->canTransitionTo(OrderStatus::Cancelled));

        $this->assertFalse(OrderStatus::Completed->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Pending));
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Completed));
        $this->assertFalse(OrderStatus::Completed->canTransitionTo(OrderStatus::Pending));

        $this->assertTrue(OrderStatus::Completed->isTerminal());
        $this->assertTrue(OrderStatus::Cancelled->isTerminal());
        $this->assertFalse(OrderStatus::Pending->isTerminal());
        $this->assertFalse(OrderStatus::Processing->isTerminal());
    }

    private function seedOrderAndInvoice(int $quantity, int $unitPriceCents): void
    {
        $this->order = Order::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => $unitPriceCents * $quantity,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => $unitPriceCents * $quantity,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => $unitPriceCents * $quantity,
            'status' => OrderStatus::Pending,
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
            'shipping_cents' => 0,
            'total_cents' => $unitPriceCents * $quantity,
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

    private function currentStock(): int
    {
        $inv = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();

        return (int) $inv->current_stock;
    }

    private function cancelUrl(int $orderId): string
    {
        return "http://panda-estampados.com/api/orders/{$orderId}/cancel";
    }
}
