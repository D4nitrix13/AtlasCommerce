<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\Returns\InvalidRefundTransitionException;
use App\Exceptions\Returns\InvalidReturnTransitionException;
use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Payments\PaymentMethod;
use App\Models\Refunds\Refund;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Inventory\StockService;
use App\Services\Payments\PaymentService;
use App\Services\Returns\RefundService;
use App\Services\Returns\ReturnRequestService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('returns')]
#[Group('inventory')]
final class RefundFlowTest extends TestCase
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

    private PaymentMethod $paymentMethod;

    private PaymentService $payments;

    private ReturnRequestService $returnRequests;

    private RefundService $refunds;

    private StockService $stock;

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
        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->paymentMethod = PaymentMethod::factory()->create();

        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['base_price_cents' => 25_00]);

        // Stock inicial = 100. La orden se venderá con 2 unidades → stock baja a 98.
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->seedOrderAndInvoice(quantity: 2, unitPriceCents: 25_00);

        app(StoreContextService::class)->setStore($this->store);

        $this->payments = app(PaymentService::class);
        $this->returnRequests = app(ReturnRequestService::class);
        $this->refunds = app(RefundService::class);
        $this->stock = app(StockService::class);
    }

    #[Test]
    public function return_request_can_be_created_with_items(): void
    {
        $returnRequest = $this->returnRequests->create(
            order: $this->order,
            reason: 'Producto defectuoso',
            items: [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        );

        $this->assertSame(ReturnRequestStatus::Pending, $returnRequest->status);
        $this->assertSame(1, $returnRequest->items()->count());
        $this->assertSame(25_00, (int) $returnRequest->items()->first()->amount_cents);
    }

    #[Test]
    public function return_request_cannot_include_product_not_in_order(): void
    {
        $otherProduct = Product::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->returnRequests->create(
            order: $this->order,
            reason: 'Test',
            items: [
                ['product_id' => $otherProduct->id, 'quantity' => 1],
            ],
        );
    }

    #[Test]
    public function return_request_cannot_be_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->returnRequests->create(
            order: $this->order,
            reason: 'Test',
            items: [],
        );
    }

    #[Test]
    public function return_request_can_be_approved(): void
    {
        $returnRequest = $this->createApprovedReturnRequest(quantity: 1);

        $this->assertSame(ReturnRequestStatus::Approved, $returnRequest->status);
        $this->assertNotNull($returnRequest->resolved_at);
    }

    #[Test]
    public function return_request_can_be_rejected(): void
    {
        $returnRequest = $this->returnRequests->create(
            order: $this->order,
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );

        $rejected = $this->returnRequests->reject(returnRequest: $returnRequest);

        $this->assertSame(ReturnRequestStatus::Rejected, $rejected->status);
        $this->assertNotNull($rejected->resolved_at);
    }

    #[Test]
    public function approved_return_request_cannot_be_approved_again(): void
    {
        $returnRequest = $this->createApprovedReturnRequest(quantity: 1);

        $this->expectException(InvalidReturnTransitionException::class);
        $this->returnRequests->approve(returnRequest: $returnRequest);
    }

    #[Test]
    public function rejected_return_request_cannot_be_approved(): void
    {
        $returnRequest = $this->returnRequests->create(
            order: $this->order,
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        $this->returnRequests->reject(returnRequest: $returnRequest);

        $this->expectException(InvalidReturnTransitionException::class);
        $this->returnRequests->approve(returnRequest: $returnRequest);
    }

    #[Test]
    public function refund_can_only_be_created_for_approved_return_request(): void
    {
        $pending = $this->returnRequests->create(
            order: $this->order,
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );

        $this->expectException(RuntimeException::class);
        $this->refunds->create(
            returnRequest: $pending,
            amountCents: 25_00,
        );
    }

    #[Test]
    public function refund_amount_must_be_positive(): void
    {
        $returnRequest = $this->createApprovedReturnRequest(quantity: 1);

        $this->expectException(RuntimeException::class);
        $this->refunds->create(
            returnRequest: $returnRequest,
            amountCents: 0,
        );
    }

    #[Test]
    public function full_refund_reverts_stock_and_updates_invoice(): void
    {
        // 1. Liquidar la invoice (decrementa stock 100→98).
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $this->assertSame(98, $this->currentStock());

        // 2. Crear y aprobar return request por 2 unidades (todas las del order).
        $returnRequest = $this->returnRequests->create(
            order: $this->order->fresh(),
            reason: 'Cambio de opinión',
            items: [['product_id' => $this->product->id, 'quantity' => 2]],
        );
        $this->returnRequests->approve(returnRequest: $returnRequest);

        // 3. Crear refund por el total: 50_00 cents (2 × 25_00).
        $refund = $this->refunds->create(
            returnRequest: $returnRequest->fresh(),
            amountCents: 50_00,
        );

        $this->assertSame(RefundStatus::Pending, $refund->status);

        // 4. Procesar el refund: stock revierte 98→100, invoice.refunded_cents=50_00, return.completed.
        $processed = $this->refunds->process(refund: $refund);

        $this->assertSame(RefundStatus::Completed, $processed->status);
        $this->assertNotNull($processed->refunded_at);

        $this->assertSame(100, $this->currentStock());

        $this->invoice->refresh();
        $this->assertSame(50_00, (int) $this->invoice->refunded_cents);
        $this->assertSame(0, $this->invoice->refundableBalanceCents());

        $returnRequest->refresh();
        $this->assertSame(ReturnRequestStatus::Completed, $returnRequest->status);

        // El inventory_movement de tipo 'return' se creó.
        $this->assertSame(1, InventoryMovement::query()
            ->where(column: 'movement_type', operator: '=', value: 'return')
            ->count());
    }

    #[Test]
    public function partial_refund_reverts_partial_stock_and_partial_invoice(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        // Devolver solo 1 de las 2 unidades (50% del order).
        $returnRequest = $this->returnRequests->create(
            order: $this->order->fresh(),
            reason: 'Solo una unidad defectuosa',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        $this->returnRequests->approve(returnRequest: $returnRequest);

        $refund = $this->refunds->create(
            returnRequest: $returnRequest->fresh(),
            amountCents: 25_00,
        );

        $this->refunds->process(refund: $refund);

        // Stock: 100 → 98 (sale) → 99 (return de 1).
        $this->assertSame(99, $this->currentStock());

        $this->invoice->refresh();
        $this->assertSame(25_00, (int) $this->invoice->refunded_cents);
        $this->assertSame(25_00, $this->invoice->refundableBalanceCents());
    }

    #[Test]
    public function refund_amount_cannot_exceed_invoice_refundable_balance(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = $this->createApprovedReturnRequest(quantity: 2);

        // Intentar refundar más que el total de la invoice.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('supera el saldo devoluble');

        $refund = $this->refunds->create(
            returnRequest: $returnRequest,
            amountCents: 99_99, // > 50_00
        );

        $this->refunds->process(refund: $refund);
    }

    #[Test]
    public function stock_reversion_is_idempotent_for_repeated_processing(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = $this->createApprovedReturnRequest(quantity: 2);

        $refund = $this->refunds->create(
            returnRequest: $returnRequest,
            amountCents: 50_00,
        );

        $this->refunds->process(refund: $refund);

        // El stock se revirtió una sola vez.
        $this->assertSame(100, $this->currentStock());

        // Llamada directa a incrementForReturnRequest NO debe duplicar el movimiento.
        $this->stock->incrementForReturnRequest(returnRequest: $returnRequest->fresh());
        $this->assertSame(100, $this->currentStock());
        $this->assertSame(1, InventoryMovement::query()
            ->where(column: 'movement_type', operator: '=', value: 'return')
            ->count());
    }

    #[Test]
    public function refund_can_be_marked_as_failed_without_reverting_stock(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = $this->createApprovedReturnRequest(quantity: 1);

        $refund = $this->refunds->create(
            returnRequest: $returnRequest,
            amountCents: 25_00,
        );

        $this->refunds->markAsFailed(refund: $refund);

        $refund->refresh();
        $this->assertSame(RefundStatus::Failed, $refund->status);

        // Stock NO se revirtió.
        $this->assertSame(98, $this->currentStock());

        // Invoice NO se impactó.
        $this->invoice->refresh();
        $this->assertSame(0, (int) $this->invoice->refunded_cents);
    }

    #[Test]
    public function completed_refund_cannot_be_reprocessed(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $returnRequest = $this->createApprovedReturnRequest(quantity: 2);

        $refund = $this->refunds->create(
            returnRequest: $returnRequest,
            amountCents: 50_00,
        );

        $this->refunds->process(refund: $refund);

        $this->expectException(InvalidRefundTransitionException::class);
        $this->refunds->process(refund: $refund->fresh());
    }

    #[Test]
    public function multiple_refunds_can_be_processed_sequentially(): void
    {
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        // Dos return requests separados, cada uno por 1 unidad.
        $rr1 = $this->returnRequests->create(
            order: $this->order->fresh(),
            reason: 'Razón 1',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        $this->returnRequests->approve(returnRequest: $rr1);

        $rr2 = $this->returnRequests->create(
            order: $this->order->fresh(),
            reason: 'Razón 2',
            items: [['product_id' => $this->product->id, 'quantity' => 1]],
        );
        $this->returnRequests->approve(returnRequest: $rr2);

        $refund1 = $this->refunds->create(returnRequest: $rr1, amountCents: 25_00);
        $refund2 = $this->refunds->create(returnRequest: $rr2, amountCents: 25_00);

        $this->refunds->process(refund: $refund1);
        $this->refunds->process(refund: $refund2);

        // Stock: 100 → 98 (sale) → 99 → 100 (ambos returns).
        $this->assertSame(100, $this->currentStock());

        $this->invoice->refresh();
        $this->assertSame(50_00, (int) $this->invoice->refunded_cents);
    }

    #[Test]
    public function return_request_machine_state_validates_transitions(): void
    {
        $this->assertTrue(ReturnRequestStatus::Pending->canTransitionTo(ReturnRequestStatus::Approved));
        $this->assertTrue(ReturnRequestStatus::Pending->canTransitionTo(ReturnRequestStatus::Rejected));
        $this->assertTrue(ReturnRequestStatus::Pending->canTransitionTo(ReturnRequestStatus::Cancelled));
        $this->assertTrue(ReturnRequestStatus::Approved->canTransitionTo(ReturnRequestStatus::Completed));
        $this->assertTrue(ReturnRequestStatus::Approved->canTransitionTo(ReturnRequestStatus::Cancelled));
        $this->assertFalse(ReturnRequestStatus::Rejected->canTransitionTo(ReturnRequestStatus::Approved));
        $this->assertFalse(ReturnRequestStatus::Completed->canTransitionTo(ReturnRequestStatus::Pending));
        $this->assertTrue(ReturnRequestStatus::Rejected->isTerminal());
        $this->assertTrue(ReturnRequestStatus::Completed->isTerminal());

        $this->assertTrue(RefundStatus::Pending->canTransitionTo(RefundStatus::Completed));
        $this->assertTrue(RefundStatus::Pending->canTransitionTo(RefundStatus::Failed));
        $this->assertFalse(RefundStatus::Completed->canTransitionTo(RefundStatus::Failed));
        $this->assertTrue(RefundStatus::Completed->isTerminal());
        $this->assertTrue(RefundStatus::Failed->isTerminal());
    }

    private function createApprovedReturnRequest(int $quantity): ReturnRequest
    {
        $rr = $this->returnRequests->create(
            order: $this->order->fresh(),
            reason: 'Test',
            items: [['product_id' => $this->product->id, 'quantity' => $quantity]],
        );

        return $this->returnRequests->approve(returnRequest: $rr);
    }

    private function currentStock(): int
    {
        $inv = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();

        return (int) $inv->current_stock;
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
            'shipping_cents' => 0,
            'total_cents' => $unitPriceCents * $quantity,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        // Items de invoice (necesarios para que el StockService decremente al pagar).
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
