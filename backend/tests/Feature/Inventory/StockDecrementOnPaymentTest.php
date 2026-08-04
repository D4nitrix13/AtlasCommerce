<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\InventoryRecordNotFoundException;
use App\Services\Inventory\StockService;
use App\Services\Payments\OverpaymentException;
use App\Services\Payments\PaymentService;
use Core\Tenant\Application\StoreContextService;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('inventory')]
#[Group('payments')]
final class StockDecrementOnPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    private Warehouse $warehouse;

    private Product $product;

    private Order $order;

    private Invoice $invoice;

    private PaymentMethod $paymentMethod;

    private PaymentService $payments;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        app(StoreContextService::class)->clear();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create();
        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->customer = Customer::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create();

        $this->product = Product::factory()
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['base_price_cents' => 25_00]);

        $this->seedOrderAndInvoice(quantity: 2, unitPriceCents: 25_00);

        app(StoreContextService::class)->setStore($this->store);

        $this->payments = app(PaymentService::class);
        $this->stock = app(StockService::class);
    }

    #[Test]
    public function full_payment_decrements_stock_for_each_invoice_item(): void
    {
        $initialStock = 100;
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: $initialStock, minimum: 5)
            ->create();

        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $inventory = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();

        $this->assertSame($initialStock - 2, $inventory->current_stock);

        $this->assertSame(1, InventoryMovement::query()
            ->where(column: 'reason', operator: 'like', value: "settlement:invoice:{$this->invoice->id}:%")
            ->count());
    }

    #[Test]
    public function partial_payment_does_not_decrement_stock(): void
    {
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: 30_00, // 50_00 total - 30_00 = 20_00 pendiente
        );

        $inventory = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();

        $this->assertSame(100, $inventory->current_stock);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    #[Test]
    public function stock_decrement_is_idempotent_on_repeated_full_payment_calls(): void
    {
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        // Primer pago: liquida la invoice.
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        // El stock bajó de 100 a 98.
        $inventory = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();
        $this->assertSame(98, $inventory->current_stock);

        // Segundo intento: el OverpaymentException bloquea el flujo a nivel de PaymentService
        // (no queda saldo pendiente). Pero incluso si llegara a decrementForInvoice,
        // el guard de idempotencia debe frenar el doble descuento.
        $this->expectException(OverpaymentException::class);
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: 1_00,
        );

        // Stock sigue en 98 — el doble decremento NO ocurrió.
        $inventory->refresh();
        $this->assertSame(98, $inventory->current_stock);
    }

    #[Test]
    public function insufficient_stock_throws_and_rolls_back_payment(): void
    {
        // Stock inicial = 1, pero la orden pide 2 unidades.
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 1, minimum: 0)
            ->create();

        try {
            $this->payments->applyPayment(
                customer: $this->customer,
                invoice: $this->invoice,
                paymentMethod: $this->paymentMethod,
                appliedAmountCents: $this->invoice->total_cents,
            );
            $this->fail('Se esperaba InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('product='.$this->product->id, $e->getMessage());
        }

        // El payment NO se creó (rollback).
        $this->assertSame(0, Payment::query()->count());

        // La invoice sigue pending.
        $raw = DB::table(table: 'invoices')->where('id', $this->invoice->id)->first();
        $this->assertNotNull($raw);
        $this->assertSame('pending', $raw->payment_status);

        // El stock NO cambió.
        $inventory = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();
        $this->assertSame(1, $inventory->current_stock);
    }

    #[Test]
    public function missing_inventory_record_throws_and_rolls_back_payment(): void
    {
        // NO sembramos Inventory para este producto en el warehouse.
        $this->expectException(InventoryRecordNotFoundException::class);

        try {
            $this->payments->applyPayment(
                customer: $this->customer,
                invoice: $this->invoice,
                paymentMethod: $this->paymentMethod,
                appliedAmountCents: $this->invoice->total_cents,
            );
        } finally {
            // El payment NO se creó (rollback).
            $this->assertSame(0, Payment::query()->count());
            $raw = DB::table(table: 'invoices')->where('id', $this->invoice->id)->first();
            $this->assertNotNull($raw);
            $this->assertSame('pending', $raw->payment_status);
        }
    }

    #[Test]
    public function two_partial_payments_decrement_stock_only_on_full_settlement(): void
    {
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        // Pago 1: 30_00 de 50_00 → partial, sin decremento.
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: 30_00,
        );

        $inventory = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $this->warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $this->product->id)
            ->firstOrFail();
        $this->assertSame(100, $inventory->current_stock);

        // Pago 2: 20_00 → completa → decremento.
        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: 20_00,
        );

        $inventory->refresh();
        $this->assertSame(98, $inventory->current_stock);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    #[Test]
    public function stock_movement_records_previous_and_new_stock(): void
    {
        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 50, minimum: 5)
            ->create();

        $this->payments->applyPayment(
            customer: $this->customer,
            invoice: $this->invoice,
            paymentMethod: $this->paymentMethod,
            appliedAmountCents: $this->invoice->total_cents,
        );

        $movement = InventoryMovement::query()
            ->where(column: 'reason', operator: 'like', value: "settlement:invoice:{$this->invoice->id}:%")
            ->firstOrFail();

        $this->assertSame('sale', $movement->movement_type);
        $this->assertSame(2, $movement->quantity);
        $this->assertSame(50, $movement->previous_stock);
        $this->assertSame(48, $movement->new_stock);
        $this->assertSame("settlement:invoice:{$this->invoice->id}:item:{$this->invoice->items->first()->id}", $movement->reason);
    }

    #[Test]
    public function stock_service_is_noop_for_invoice_without_items(): void
    {
        // Invoice sin items: la dejamos vacía quitando los items creados en setUp.
        InvoiceItem::query()->where('invoice_id', $this->invoice->id)->delete();
        OrderItem::query()->where('order_id', $this->order->id)->delete();

        $this->invoice->refresh();
        $this->invoice->items()->delete();

        // No debe lanzar nada.
        $this->stock->decrementForInvoice(invoice: $this->invoice);

        // Ningún movimiento creado.
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    #[Test]
    public function stock_service_throws_when_no_active_warehouse_configured(): void
    {
        $this->warehouse->update(['is_active' => false]);

        Inventory::factory()
            ->for($this->warehouse)
            ->for($this->product)
            ->withStock(current: 100, minimum: 5)
            ->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay almacén activo');

        $this->stock->decrementForInvoice(invoice: $this->invoice);
    }

    /**
     * Crea una Order + Invoice + OrderItem + InvoiceItem
     * con los datos estándar de los tests de esta clase.
     */
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
