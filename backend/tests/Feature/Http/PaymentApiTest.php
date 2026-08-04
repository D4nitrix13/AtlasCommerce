<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Auth\User;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('http')]
#[Group('payments')]
final class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Store $store;

    private Customer $customer;

    private User $user;

    private Order $order;

    private Invoice $invoice;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset StoreContextService para que el global scope de BelongsToStore
        // no se filtre por una tienda de un test anterior.
        app(StoreContextService::class)->clear();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()->for(factory: $this->currency, relationship: 'currency')->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $address = CustomerAddress::factory()->for($this->customer)->create();
        $this->paymentMethod = PaymentMethod::factory()->create();

        // Crear orden + invoice para los pagos.
        $product = Product::factory()
            ->for(factory: Category::factory(), relationship: 'category')
            ->for(factory: $this->currency, relationship: 'currency')
            ->create(['base_price_cents' => 50_00]);

        $this->order = Order::create([
            'customer_id' => $this->customer->id,
            'shipping_address_id' => $address->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 50_00,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 50_00,
            'status' => 'pending',
            'ordered_at' => now(),
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
            'total_cents' => 50_00,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        app(StoreContextService::class)->setStore($this->store);
    }

    #[Test]
    public function guest_cannot_pay(): void
    {
        $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 10_00,
        ])->assertUnauthorized();
    }

    #[Test]
    public function partial_payment_marks_invoice_as_partial(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 20_00,
            'reference' => 'TRF-001',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.amount.cents', 2000);
        // Payment.status queda en 'pending' cuando solo es partial (no full).
        $response->assertJsonPath('data.status', 'pending');

        // La invoice, en cambio, refleja 'partial' en su payment_status.
        $raw = DB::table('invoices')
            ->where('id', $this->invoice->id)
            ->first();
        $this->assertSame('partial', $raw->payment_status);

        $sum = (int) DB::table('invoice_payments')
            ->where('invoice_id', $this->invoice->id)
            ->sum('applied_amount_cents');
        $this->assertSame(20_00, $sum);
    }

    #[Test]
    public function full_payment_marks_invoice_as_paid_and_payment_completed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 50_00,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'completed');

        $this->invoice->refresh();
        $this->assertSame('paid', $this->invoice->payment_status);
        $this->assertSame(0, $this->invoice->pendingBalanceCents());

        $payment = Payment::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertSame('completed', $payment->status);
    }

    #[Test]
    public function overpayment_is_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 51_00, // 1 cent over
        ])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains((string) $m, 'supera'));

        $this->invoice->refresh();
        $this->assertSame(0, $this->invoice->paidAmountCents());
        $this->assertSame('pending', $this->invoice->payment_status);
    }

    #[Test]
    public function cannot_pay_another_customers_invoice(): void
    {
        Sanctum::actingAs($this->user);

        $otherCustomer = Customer::factory()->create();
        $otherInvoice = Invoice::create([
            'order_id' => $this->order->id,
            'customer_id' => $otherCustomer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-OTHER',
            'subtotal_cents' => 50_00,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 50_00,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->postJson('/api/payments', [
            'invoice_id' => $otherInvoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 10_00,
        ])->assertForbidden();
    }

    #[Test]
    public function two_partial_payments_drive_status_to_paid(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 30_00,
        ])->assertCreated();

        $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 20_00,
        ])->assertCreated();

        $this->invoice->refresh();
        $this->assertSame(50_00, $this->invoice->paidAmountCents());
        $this->assertSame('paid', $this->invoice->payment_status);

        // Dos payments creados, ambos con status completed porque la invoice quedó pagada.
        $this->assertSame(2, Payment::where('customer_id', $this->customer->id)->count());
    }
}
