<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Customers\Customer;
use App\Models\Payments\InvoicePayment;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Services\Inventory\StockService;
use App\Services\Shipping\ShipmentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio de aplicación de pagos a facturas.
 *
 * Reglas de negocio:
 *  - El pago SIEMPRE se aplica a una sola invoice por request (MVP).
 *  - `applied_amount_cents` no puede superar el `pending_balance_cents`.
 *    Si lo supera, lanza `OverpaymentException` (422).
 *  - `applied_amount_cents` debe ser > 0 (>= 1 cent).
 *  - El `Payment` hereda la currency de la invoice (no se permite pago
 *    en otra currency en MVP).
 *  - `payment_status` de la invoice se recalcula atómicamente:
 *      paid == total → 'paid'
 *      paid > 0     → 'partial'
 *      paid == 0    → 'pending'
 *  - `Payment.status` queda en 'completed' si la invoice quedó
 *    completamente pagada, sino 'pending' (no bloqueante).
 *
 * El método es transaccional: si algo falla, se hace rollback total.
 */
final class PaymentService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly ShipmentService $shipments,
    ) {}

    /**
     * Aplica un pago a una invoice.
     *
     * @param  Customer  $customer  Cliente que paga (dueño de la invoice).
     * @param  Invoice  $invoice  Invoice a liquidar.
     * @param  PaymentMethod  $paymentMethod  Método de pago usado.
     * @param  int  $appliedAmountCents  Centavos que se aplican ahora.
     * @param  string|null  $reference  Referencia externa (transferencia, cheque, etc.).
     * @param  string|null  $notes  Notas opcionales.
     */
    public function applyPayment(
        Customer $customer,
        Invoice $invoice,
        PaymentMethod $paymentMethod,
        int $appliedAmountCents,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        if ($appliedAmountCents <= 0) {
            throw new RuntimeException(
                'El monto del pago debe ser mayor a 0 centavos.',
            );
        }

        return DB::transaction(callback: function () use (
            $customer,
            $invoice,
            $paymentMethod,
            $appliedAmountCents,
            $reference,
            $notes,
        ): Payment {
            // Lock pesimista sobre la invoice para evitar race conditions
            // cuando dos pagos concurrentes podrían sobrepasar el total.
            $locked = Invoice::query()
                ->where('id', $invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pending = $locked->pendingBalanceCents();
            if ($appliedAmountCents > $pending) {
                throw new OverpaymentException(
                    "El pago de {$appliedAmountCents} centavos supera el saldo pendiente de {$pending} centavos.",
                );
            }

            // Crea el Payment con la currency de la invoice.
            $payment = Payment::create([
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethod->id,
                'store_id' => $locked->store_id,
                'currency_id' => $locked->currency_id,
                'amount_cents' => $appliedAmountCents,
                'exchange_rate_snapshot' => (string) $locked->exchange_rate_snapshot,
                'reference' => $reference,
                'status' => 'pending',
                'paid_at' => now(),
                'notes' => $notes,
            ]);

            // Pivot: aplica el pago a esta invoice.
            InvoicePayment::create([
                'invoice_id' => $locked->id,
                'payment_id' => $payment->id,
                'applied_amount_cents' => $appliedAmountCents,
                'applied_at' => now(),
            ]);

            // Recalcula el estado de la invoice desde el pivot (single source of truth).
            $locked->refresh();
            $paid = $locked->paidAmountCents();

            $newStatus = match (true) {
                $paid >= $locked->total_cents => 'paid',
                $paid > 0 => 'partial',
                default => 'pending',
            };

            // PHASE 9: el stock se decrementa SOLO cuando la invoice
            // pasa a 'paid'. Pagos parciales no mueven stock — el riesgo
            // de venta sin entregar ya está cubierto por la idempotencia
            // de la invoice y por el lock pesimista.
            $becameFullyPaid = $newStatus === 'paid';

            $locked->update([
                'payment_status' => $newStatus,
            ]);

            if ($becameFullyPaid) {
                $payment->update(['status' => 'completed']);

                $this->stock->decrementForInvoice(
                    invoice: $locked,
                    user: auth()->user(),
                );

                $this->shipments->createForInvoice(invoice: $locked);
            }

            return $payment->fresh(['invoicePayments']);
        });
    }
}
