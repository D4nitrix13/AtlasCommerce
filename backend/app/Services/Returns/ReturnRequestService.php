<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Enums\ReturnRequestStatus;
use App\Exceptions\Returns\InvalidReturnTransitionException;
use App\Models\Returns\ReturnRequest;
use App\Models\Returns\ReturnRequestItem;
use App\Models\Sales\Order;
use App\Models\Sales\OrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio de gestión de solicitudes de devolución.
 *
 * Responsabilidades:
 *  - Crear return requests con sus items (snapshot del precio al momento de la venta).
 *  - Transiciones de estado: approve, reject, complete, cancel.
 *
 * Reglas:
 *  - El return request debe estar asociado a un Order existente.
 *  - Cada item referencia un product del order (validación de pertenencia).
 *  - Solo se puede aprobar/rechazar si está `pending`.
 *  - Solo se puede completar si está `approved` (transición manual o
 *    disparada por `RefundService::process()`).
 */
final class ReturnRequestService
{
    /**
     * Crea una solicitud de devolución.
     *
     * @param  Order  $order  Orden original de la venta.
     * @param  string  $reason  Razón de la devolución.
     * @param  array<int, array{product_id: int, product_variant_id?: int|null, quantity: int}>  $items
     *                                                                                                   Items a devolver. El monto se snapshot-ea del precio original del order.
     * @param  int|null  $customerId  Si difiere del cliente de la orden (admin crea en nombre).
     *
     * @throws RuntimeException Si la orden no tiene items o si un product no pertenece a la orden.
     */
    public function create(
        Order $order,
        string $reason,
        array $items,
        ?int $customerId = null,
    ): ReturnRequest {
        if ($items === []) {
            throw new RuntimeException('La solicitud de devolución debe incluir al menos un item.');
        }

        $orderItemsByKey = $this->loadOrderItemsIndex(order: $order);

        return DB::transaction(callback: function () use ($order, $reason, $items, $customerId, $orderItemsByKey): ReturnRequest {
            $returnRequest = ReturnRequest::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'customer_id' => $customerId ?? $order->customer_id,
                'reason' => $reason,
                'status' => ReturnRequestStatus::Pending,
            ]);

            foreach ($items as $payload) {
                $productId = (int) $payload['product_id'];
                $variantId = isset($payload['product_variant_id']) ? (int) $payload['product_variant_id'] : null;
                $quantity = (int) $payload['quantity'];

                $key = $this->orderItemKey(productId: $productId, variantId: $variantId);

                if (! isset($orderItemsByKey[$key])) {
                    throw new RuntimeException(
                        "El product={$productId} (variant=".($variantId ?? 'null').') '
                        ."no pertenece a la orden {$order->id}.",
                    );
                }

                /** @var OrderItem $orderItem */
                $orderItem = $orderItemsByKey[$key];

                ReturnRequestItem::create([
                    'return_request_id' => $returnRequest->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'amount_cents' => (int) $orderItem->unit_price_cents * $quantity,
                ]);
            }

            return $returnRequest->fresh(['items']);
        });
    }

    public function approve(ReturnRequest $returnRequest): ReturnRequest
    {
        return $this->resolve(returnRequest: $returnRequest, to: ReturnRequestStatus::Approved);
    }

    public function reject(ReturnRequest $returnRequest): ReturnRequest
    {
        return $this->resolve(returnRequest: $returnRequest, to: ReturnRequestStatus::Rejected);
    }

    public function cancel(ReturnRequest $returnRequest): ReturnRequest
    {
        return $this->resolve(returnRequest: $returnRequest, to: ReturnRequestStatus::Cancelled);
    }

    /**
     * Marca la solicitud como completada (después de procesar el refund).
     */
    public function complete(ReturnRequest $returnRequest): ReturnRequest
    {
        $this->transition(returnRequest: $returnRequest, to: ReturnRequestStatus::Completed);

        return $returnRequest->fresh();
    }

    /**
     * Resuelve una solicitud de devolución: valida transición y marca `resolved_at`.
     *
     * Para transiciones que NO terminan la solicitud (todavía no hay
     * `resolved_at` hasta que se rechace/apruebe), usar `complete()`.
     */
    private function resolve(ReturnRequest $returnRequest, ReturnRequestStatus $to): ReturnRequest
    {
        $this->transition(returnRequest: $returnRequest, to: $to);

        $returnRequest->update([
            'resolved_at' => now(),
        ]);

        return $returnRequest->fresh();
    }

    private function transition(ReturnRequest $returnRequest, ReturnRequestStatus $to): void
    {
        $current = $returnRequest->status;

        if (! $current->canTransitionTo($to)) {
            throw InvalidReturnTransitionException::fromTo(from: $current, to: $to);
        }

        $returnRequest->update(['status' => $to]);
    }

    /**
     * Carga todos los items de la orden indexados por (product_id, variant_id|null).
     *
     * Evita N+1 al validar pertenencia de cada item del return request.
     *
     * @return array<string, OrderItem>
     */
    private function loadOrderItemsIndex(Order $order): array
    {
        $order->loadMissing('items');

        $index = [];

        foreach ($order->items as $item) {
            $index[$this->orderItemKey(
                productId: (int) $item->product_id,
                variantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
            )] = $item;
        }

        return $index;
    }

    private function orderItemKey(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?? 'null');
    }
}
