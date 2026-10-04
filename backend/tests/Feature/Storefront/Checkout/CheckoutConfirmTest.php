<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront\Checkout;

use App\Enums\OrderStatus;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Sales\OrderStatusHistory;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use App\Models\Stores\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test as TestAttr;
use Tests\TestCase;

/**
 * Tests del endpoint `POST /api/v1/storefront/checkout/confirm/{orderId}`.
 *
 * Cubre el webhook payment stub que acredita un pago y dispara la
 * transición de estado de la orden:
 *  - Happy path: payment succeeded → orden Pending pasa a Processing
 *    y se registra el cambio en `order_status_histories`.
 *  - Idempotencia: el mismo `payment_intent_id` enviado dos veces
 *    responde 200 ambas veces sin side effects adicionales.
 *  - Estado terminal: orden ya cancelada → 409, sin mutaciones.
 *  - payment_intent_id desconocido → 404.
 */
#[Group('storefront')]
#[Group('checkout')]
final class CheckoutConfirmTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Store $store;

    private Currency $currency;

    private PaymentMethod $paymentMethod;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::factory()->primary()->create();
        $this->store = Store::factory()
            ->for($this->currency, 'currency')
            ->create(attributes: ['slug' => 'panda-estampados']);

        $this->host = 'panda-estampados.test';
        StoreDomain::query()->create([
            'store_id' => $this->store->id,
            'domain' => $this->host,
            'type' => 'domain',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(attributes: ['user_id' => $this->user->id]);
        $this->paymentMethod = PaymentMethod::factory()->create();
    }

    #[TestAttr]
    public function it_confirms_payment_and_transitions_order_to_processing(): void
    {
        $paymentIntentId = 'pi_test_happy_path';
        [$order, $payment] = $this->seedOrderWithPendingPayment(
            paymentIntentId: $paymentIntentId,
        );

        Sanctum::actingAs(user: $this->user);

        $response = $this->postJson(
            uri: $this->confirmUrl($order->id),
            data: [
                'payment_intent_id' => $paymentIntentId,
                'status' => 'succeeded',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath(path: 'data.order_status', expect: OrderStatus::Processing->value);
        $response->assertJsonPath(path: 'data.status', expect: 'succeeded');
        $response->assertJsonPath(path: 'data.idempotent', expect: null);

        $this->assertDatabaseHas(table: 'payments', data: [
            'id' => $payment->id,
            'status' => 'succeeded',
        ]);
        $this->assertNotNull($payment->fresh()->paid_at);

        $this->assertDatabaseHas(table: 'orders', data: [
            'id' => $order->id,
            'status' => OrderStatus::Processing->value,
        ]);

        $this->assertDatabaseHas(table: 'order_status_histories', data: [
            'order_id' => $order->id,
            'previous_status' => OrderStatus::Pending->value,
            'new_status' => OrderStatus::Processing->value,
            'observation' => 'Pago confirmado vía PSP webhook',
        ]);
    }

    #[TestAttr]
    public function it_is_idempotent_on_duplicate_webhook(): void
    {
        $paymentIntentId = 'pi_test_idempotent';
        [$order, $payment] = $this->seedOrderWithPendingPayment(
            paymentIntentId: $paymentIntentId,
        );

        Sanctum::actingAs(user: $this->user);

        // Primer webhook: debe transicionar.
        $first = $this->postJson(uri: $this->confirmUrl($order->id), data: [
            'payment_intent_id' => $paymentIntentId,
            'status' => 'succeeded',
        ]);
        $first->assertOk();
        $first->assertJsonPath(path: 'data.order_status', expect: OrderStatus::Processing->value);

        $historyCountAfterFirst = OrderStatusHistory::query()
            ->where(column: 'order_id', operator: '=', value: $order->id)
            ->count();

        // Segundo webhook (mismo payment_intent_id): debe ser no-op.
        $second = $this->postJson(uri: $this->confirmUrl($order->id), data: [
            'payment_intent_id' => $paymentIntentId,
            'status' => 'succeeded',
        ]);
        $second->assertOk();
        $second->assertJsonPath(path: 'data.idempotent', expect: true);
        $second->assertJsonPath(path: 'data.order_status', expect: OrderStatus::Processing->value);

        // Sin nuevos registros de status history ni cambios en paid_at.
        $this->assertSame(
            $historyCountAfterFirst,
            OrderStatusHistory::query()
                ->where(column: 'order_id', operator: '=', value: $order->id)
                ->count(),
        );

        $payment->refresh();
        $this->assertSame('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    #[TestAttr]
    public function it_returns_409_when_order_already_cancelled(): void
    {
        $paymentIntentId = 'pi_test_cancelled';
        [$order, $payment] = $this->seedOrderWithPendingPayment(
            paymentIntentId: $paymentIntentId,
            orderStatus: OrderStatus::Cancelled,
        );

        Sanctum::actingAs(user: $this->user);

        $response = $this->postJson(uri: $this->confirmUrl($order->id), data: [
            'payment_intent_id' => $paymentIntentId,
            'status' => 'succeeded',
        ]);

        $response->assertStatus(status: 409);
        $response->assertJsonPath(
            path: 'message',
            expect: "La orden #{$order->id} está en estado terminal 'cancelled' y no puede modificarse.",
        );

        // El payment NO debe haber sido tocado.
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
    }

    #[TestAttr]
    public function it_returns_404_when_payment_intent_id_unknown(): void
    {
        [$order] = $this->seedOrderWithPendingPayment(
            paymentIntentId: 'pi_real',
        );

        Sanctum::actingAs(user: $this->user);

        $response = $this->postJson(uri: $this->confirmUrl($order->id), data: [
            'payment_intent_id' => 'pi_desconocido',
            'status' => 'succeeded',
        ]);

        $response->assertStatus(status: 404);
        $response->assertJsonPath(
            path: 'message',
            expect: 'No se encontró el pago asociado a este payment_intent_id.',
        );

        // La orden no debe haberse transicionado.
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    /**
     * Crea una orden con su invoice y un Payment `pending` ligado a esa
     * invoice vía `invoice_payments` (mismo pivot que usa el flow real).
     *
     * @return array{0: Order, 1: Payment}
     */
    private function seedOrderWithPendingPayment(
        string $paymentIntentId,
        OrderStatus $orderStatus = OrderStatus::Pending,
    ): array {
        $address = CustomerAddress::factory()
            ->for($this->customer)
            ->create();

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'shipping_address_id' => $address->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'order_number' => 'ORD-'.uniqid(),
            'subtotal_cents' => 10_000,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 10_000,
            'exchange_rate_snapshot' => 1.0,
            'total_in_base_currency_cents' => 10_000,
            'status' => $orderStatus,
            'ordered_at' => now(),
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal_cents' => 10_000,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'total_cents' => 10_000,
            'exchange_rate_snapshot' => 1.0,
            'payment_status' => 'pending',
            'invoice_status' => 'issued',
            'issued_at' => now(),
        ]);

        $payment = Payment::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'currency_id' => $this->currency->id,
            'payment_method_id' => $this->paymentMethod->id,
            'amount_cents' => 10_000,
            'exchange_rate_snapshot' => 1.0,
            'reference' => $paymentIntentId,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $payment->invoices()->attach($invoice->id, [
            'applied_amount_cents' => $order->total_cents,
        ]);

        return [$order, $payment];
    }

    private function confirmUrl(int $orderId): string
    {
        return "http://{$this->host}/api/v1/storefront/checkout/confirm/{$orderId}";
    }
}
