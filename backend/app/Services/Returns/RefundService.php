<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Enums\RefundStatus;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\Returns\InvalidRefundTransitionException;
use App\Models\Payments\PaymentMethod;
use App\Models\Returns\Refund;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\Invoice;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio de procesamiento de reembolsos.
 *
 * Responsabilidades:
 *  - Crear un Refund `pending` vinculado a un return request aprobado.
 *  - Procesar el refund: marca `completed`, revierte stock, actualiza
 *    `invoice.refunded_cents`, y marca el return request como `completed`.
 *  - Marcar refunds como `failed` (revierte sin alterar stock/invoice).
 *
 * Reglas:
 *  - Solo se procesa un refund si el return request está `approved`.
 *  - El monto del refund no puede superar el `refundable_balance_cents`
 *    de la invoice (acumulativo entre refunds del mismo return).
 *  - El refund usa la misma currency que la invoice original.
 *  - El método `process()` es transaccional: si algo falla, se hace rollback
 *    completo (no se queda en un estado parcial).
 */
final class RefundService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly ReturnRequestService $returnRequests,
    ) {}

    /**
     * Crea un refund `pending` para el return request.
     */
    public function create(
        ReturnRequest $returnRequest,
        int $amountCents,
        ?PaymentMethod $paymentMethod = null,
    ): Refund {
        if ($returnRequest->status !== ReturnRequestStatus::Approved) {
            throw new RuntimeException(
                "Solo se pueden crear refunds para return requests en estado 'approved' "
                ."(actual: {$returnRequest->status->value}).",
            );
        }

        if ($amountCents <= 0) {
            throw new RuntimeException('El monto del refund debe ser mayor a 0 centavos.');
        }

        $invoice = $this->latestInvoiceForOrder(orderId: (int) $returnRequest->order_id);

        return Refund::create([
            'store_id' => $returnRequest->store_id,
            'return_request_id' => $returnRequest->id,
            'payment_method_id' => $paymentMethod?->id,
            'currency_id' => $invoice->currency_id,
            'amount_cents' => $amountCents,
            'exchange_rate_snapshot' => (string) $invoice->exchange_rate_snapshot,
            'status' => RefundStatus::Pending,
        ]);
    }

    /**
     * Procesa el refund: marca `completed`, revierte stock, impacta invoice.
     *
     * Transaccional: cualquier fallo hace rollback total.
     */
    public function process(Refund $refund): Refund
    {
        $returnRequest = $refund->returnRequest;
        $invoice = $this->latestInvoiceForOrder(orderId: (int) $returnRequest->order_id);

        return DB::transaction(callback: function () use ($refund, $returnRequest, $invoice): Refund {
            // Validar la transición de estado ANTES de tocar datos.
            // Si el refund ya está completado/fallido, esto lanza
            // InvalidRefundTransitionException sin alterar nada.
            $this->transition(refund: $refund, to: RefundStatus::Completed);

            // Lock pesimista sobre la invoice: serializa refunds concurrentes
            // contra el mismo saldo devoluble. Sin esto, dos refunds podrían
            // pasar la validación de balance y sumar más que `refundable_balance_cents`.
            $lockedInvoice = Invoice::query()
                ->where(column: 'id', operator: '=', value: $invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->amount_cents > $lockedInvoice->refundableBalanceCents()) {
                throw new RuntimeException(
                    "El refund de {$refund->amount_cents} centavos supera el saldo devoluble "
                    ."de {$lockedInvoice->refundableBalanceCents()} centavos.",
                );
            }

            $refund->update([
                'refunded_at' => now(),
            ]);

            $lockedInvoice->update([
                'refunded_cents' => (int) $lockedInvoice->refunded_cents + $refund->amount_cents,
            ]);

            // La reversión de stock tiene su propia idempotencia por
            // `return:request:{id}:*` — safe llamarla aquí.
            $this->stock->incrementForReturnRequest(returnRequest: $returnRequest);

            $this->returnRequests->complete(returnRequest: $returnRequest);

            return $refund->fresh();
        });
    }

    /**
     * Marca el refund como fallido (no revierte stock, no impacta invoice).
     */
    public function markAsFailed(Refund $refund): Refund
    {
        $this->transition(refund: $refund, to: RefundStatus::Failed);

        return $refund->fresh();
    }

    private function transition(Refund $refund, RefundStatus $to): void
    {
        $current = $refund->status;

        if (! $current->canTransitionTo($to)) {
            throw InvalidRefundTransitionException::fromTo(from: $current, to: $to);
        }

        $refund->update(['status' => $to]);
    }

    /**
     * Resuelve la invoice más reciente del order.
     *
     * Helper centralizado para evitar N+1 (no carga la relación `order` lazy).
     * Para MVP se toma la invoice con id más alto; en una versión con
     * anulación de facturas se deberá agregar FK explícita en `refunds`.
     */
    private function latestInvoiceForOrder(int $orderId): Invoice
    {
        $invoice = Invoice::query()
            ->where(column: 'order_id', operator: '=', value: $orderId)
            ->latest('id')
            ->first();

        if ($invoice === null) {
            throw new RuntimeException(
                "La orden {$orderId} no tiene invoices asociadas.",
            );
        }

        return $invoice;
    }
}
