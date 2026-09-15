<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\Sales\InvalidOrderTransitionException;
use App\Exceptions\Sales\OrderNotCancellableException;
use App\Models\Auth\User;
use App\Models\Sales\Order;
use App\Models\Sales\OrderStatusHistory;
use App\Services\Inventory\StockService;
use App\Services\Shipping\ShipmentService;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de ciclo de vida de pedidos.
 *
 * Responsabilidades:
 *  - Cancelar una orden (`cancel()`): transición a `Cancelled` con rollback
 *    completo de shipments pendientes, cancelación de invoices, reversión de
 *    stock y registro en `order_status_histories`.
 *
 * Reglas:
 *  - El método `cancel()` es transaccional: cualquier fallo hace rollback total.
 *  - No se puede cancelar una orden con shipments ya despachados (esos van
 *    por el flujo de returns/refunds).
 *  - Para invoices con pagos aplicados, el monto pagado se refleja como
 *    `refunded_cents` adicional (no se crea un Refund explícito — el evento
 *    queda registrado en `order_status_histories`).
 *  - El revert de stock es idempotente vía `StockService::incrementForOrderCancellation()`.
 */
final class OrderService
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly StockService $stock,
    ) {}

    /**
     * Cancela una orden (cancelación hard con rollback completo).
     *
     * Pasos:
     *  1. Valida la transición de estado vía `OrderStatus::canTransitionTo`.
     *  2. Verifica que ningún shipment esté en estado no cancelable (shipped+).
     *  3. Cancela shipments pendientes.
     *  4. Para cada invoice: si tiene pagos aplicados refleja el monto en
     *     `refunded_cents`; luego marca `invoice_status = cancelled`.
     *  5. Revierte stock vía `StockService::incrementForOrderCancellation`.
     *  6. Marca la orden como `Cancelled`.
     *  7. Inserta `OrderStatusHistory` con usuario y observación.
     *
     * @throws InvalidOrderTransitionException Si la orden ya está en estado terminal.
     * @throws OrderNotCancellableException Si algún shipment no es cancelable.
     */
    public function cancel(Order $order, ?User $user = null, ?string $observation = null): Order
    {
        return DB::transaction(function () use ($order, $user, $observation): Order {
            $current = $order->status;

            if (! $current->canTransitionTo(next: OrderStatus::Cancelled)) {
                throw InvalidOrderTransitionException::fromTo(
                    from: $current,
                    to: OrderStatus::Cancelled,
                );
            }

            $shipments = $order->shipments()->get();

            foreach ($shipments as $shipment) {
                $shipmentStatus = $shipment->status;

                if ($shipmentStatus !== ShipmentStatus::Pending
                    && $shipmentStatus !== ShipmentStatus::Cancelled) {
                    throw OrderNotCancellableException::dueToShipmentInTransit(
                        shipmentStatus: $shipmentStatus->value,
                    );
                }
            }

            foreach ($shipments as $shipment) {
                if ($shipment->status === ShipmentStatus::Pending) {
                    $this->shipments->cancel(shipment: $shipment);
                }
            }

            $invoices = $order->invoices()->get();

            foreach ($invoices as $invoice) {
                $paidCents = $invoice->paidAmountCents();

                if ($paidCents > 0) {
                    $invoice->update([
                        'refunded_cents' => (int) $invoice->refunded_cents + $paidCents,
                    ]);
                }

                $invoice->update(['invoice_status' => 'cancelled']);
            }

            $this->stock->incrementForOrderCancellation(order: $order);

            $order->update(['status' => OrderStatus::Cancelled]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $user?->id,
                'previous_status' => $current->value,
                'new_status' => OrderStatus::Cancelled->value,
                'observation' => $observation,
                'changed_at' => now(),
            ]);

            return $order->fresh(['items', 'invoices', 'shipments']);
        });
    }
}
