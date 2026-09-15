<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use RuntimeException;

/**
 * Servicio de stock para movimientos asociados al ciclo de venta.
 *
 * Responsabilidad: traducir eventos de venta (liquidación de invoice,
 * devoluciones) en decrementos / incrementos de inventario con
 * trazabilidad completa vía `InventoryMovement`.
 *
 * Reglas de negocio:
 *  - La operación es transaccional y se invoca DENTRO de la transacción
 *    del caller (no abre una nueva).
 *  - Idempotente por invoice_id / return_request_id: si ya existe un
 *    movimiento con `reason` prefijado, no se hace nada.
 *  - Decrementa `current_stock` (no toca `reserved_stock` — la reserva
 *    es un feature de PHASE futura).
 *  - Valida stock disponible (current_stock - reserved_stock >= quantity).
 *    Si no, lanza `InsufficientStockException`.
 *  - Si no existe el registro de `Inventory` para una combinación
 *    (warehouse, product, variant), lanza `InventoryRecordNotFoundException`.
 *  - Si la invoice / return request no tiene items, retorna silenciosamente.
 */
final class StockService
{
    private const MOVEMENT_TYPE_SALE = 'sale';

    private const MOVEMENT_TYPE_RETURN = 'return';

    private const MOVEMENT_TYPE_CANCELLATION = 'cancellation';

    /**
     * Decrementa el stock de todos los items de una invoice liquidada.
     *
     * Para MVP usa el primer almacén activo. En multi-warehouse el caller
     * pasará el warehouse explícito.
     *
     * @throws RuntimeException Cuando no hay almacén activo configurado.
     * @throws InventoryRecordNotFoundException
     * @throws InsufficientStockException
     */
    public function decrementForInvoice(Invoice $invoice, ?User $user = null): void
    {
        $invoice->loadMissing('items');

        if ($invoice->items->isEmpty()) {
            return;
        }

        if ($this->alreadyProcessed(prefix: "settlement:invoice:{$invoice->id}:")) {
            return;
        }

        $warehouse = $this->resolveDefaultWarehouse();

        if ($warehouse === null) {
            throw new RuntimeException(
                'No hay almacén activo configurado. Configure uno antes de aceptar ventas.',
            );
        }

        foreach ($invoice->items as $item) {
            $this->applyChange(
                productId: (int) $item->product_id,
                productVariantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                quantity: (int) $item->quantity,
                warehouse: $warehouse,
                movementType: self::MOVEMENT_TYPE_SALE,
                reason: "settlement:invoice:{$invoice->id}:item:{$item->id}",
                user: $user,
                direction: -1,
            );
        }
    }

    /**
     * Incrementa el stock de los items de un return request aprobado.
     *
     * @throws RuntimeException Cuando no hay almacén activo configurado.
     * @throws InventoryRecordNotFoundException
     */
    public function incrementForReturnRequest(ReturnRequest $returnRequest): void
    {
        $returnRequest->loadMissing('items');

        if ($returnRequest->items->isEmpty()) {
            return;
        }

        if ($this->alreadyProcessed(prefix: "return:request:{$returnRequest->id}:")) {
            return;
        }

        $warehouse = $this->resolveDefaultWarehouse();

        if ($warehouse === null) {
            throw new RuntimeException(
                'No hay almacén activo configurado. Configure uno antes de aceptar devoluciones.',
            );
        }

        foreach ($returnRequest->items as $item) {
            $this->applyChange(
                productId: (int) $item->product_id,
                productVariantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                quantity: (int) $item->quantity,
                warehouse: $warehouse,
                movementType: self::MOVEMENT_TYPE_RETURN,
                reason: "return:request:{$returnRequest->id}:item:{$item->id}",
                user: null,
                direction: 1,
            );
        }
    }

    /**
     * Revierte el stock descontado al liquidar las invoices de una orden cancelada.
     *
     * Espejo de `decrementForInvoice()` pero en dirección +1. La idempotencia
     * se ancla al id de la orden (no al de la invoice) porque una cancelación
     * puede revertir stock asociado a múltiples invoices de la misma orden.
     *
     * @throws RuntimeException Cuando no hay almacén activo configurado.
     * @throws InventoryRecordNotFoundException
     */
    public function incrementForOrderCancellation(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return;
        }

        if ($this->alreadyProcessed(prefix: "cancellation:order:{$order->id}:")) {
            return;
        }

        $warehouse = $this->resolveDefaultWarehouse();

        if ($warehouse === null) {
            throw new RuntimeException(
                'No hay almacén activo configurado. Configure uno antes de aceptar cancelaciones.',
            );
        }

        foreach ($order->items as $item) {
            $this->applyChange(
                productId: (int) $item->product_id,
                productVariantId: $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                quantity: (int) $item->quantity,
                warehouse: $warehouse,
                movementType: self::MOVEMENT_TYPE_CANCELLATION,
                reason: "cancellation:order:{$order->id}:item:{$item->id}",
                user: null,
                direction: 1,
            );
        }
    }

    /**
     * Verifica si ya existe un movimiento con el prefijo de reason dado.
     *
     * Centraliza la lógica de idempotencia para decrementos y reversiones:
     * cualquier prefijo único por unidad de negocio (invoice o return request)
     * funciona.
     */
    private function alreadyProcessed(string $prefix): bool
    {
        return InventoryMovement::query()
            ->where(column: 'reason', operator: 'like', value: "{$prefix}%")
            ->exists();
    }

    /**
     * Aplica un cambio de stock (decremento o incremento) sobre una combinación
     * específica de producto/variante.
     *
     * Usa `lockForUpdate` para serializar operaciones concurrentes sobre
     * el mismo row de inventario.
     *
     * @param  int  $direction  -1 para decremento (sale), +1 para incremento (return).
     *
     * @throws InventoryRecordNotFoundException
     * @throws InsufficientStockException Solo aplica cuando direction = -1.
     */
    private function applyChange(
        int $productId,
        ?int $productVariantId,
        int $quantity,
        Warehouse $warehouse,
        string $movementType,
        string $reason,
        ?User $user,
        int $direction,
    ): void {
        $query = Inventory::query()
            ->where(column: 'warehouse_id', operator: '=', value: $warehouse->id)
            ->where(column: 'product_id', operator: '=', value: $productId);

        if ($productVariantId === null) {
            $query->whereNull(columns: 'product_variant_id');
        } else {
            $query->where(column: 'product_variant_id', operator: '=', value: $productVariantId);
        }

        $inventory = $query->lockForUpdate()->first();

        if ($inventory === null) {
            throw new InventoryRecordNotFoundException(
                "No hay registro de inventario para product={$productId}, "
                .'variant='.($productVariantId ?? 'null')." en warehouse={$warehouse->id}.",
            );
        }

        $previousStock = (int) $inventory->current_stock;

        if ($direction === -1) {
            $reserved = (int) $inventory->reserved_stock;
            $available = $previousStock - $reserved;

            if ($available < $quantity) {
                throw new InsufficientStockException(
                    "Stock insuficiente para product={$productId} (solicitado={$quantity}, "
                    ."disponible={$available}).",
                );
            }
        }

        $newStock = $previousStock + ($direction * $quantity);

        $inventory->update([
            'current_stock' => $newStock,
        ]);

        InventoryMovement::create([
            'inventory_id' => $inventory->id,
            'user_id' => $user?->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'reason' => $reason,
            'movement_date' => now(),
        ]);
    }

    /**
     * Resuelve el almacén por defecto para decrementos / incrementos automáticos.
     *
     * MVP: el primer almacén activo (sin soft-delete). En PHASE futura este
     * método recibirá el `store_id` de la invoice y resolverá el almacén
     * de la tienda (futura relación `warehouse.store_id`).
     */
    private function resolveDefaultWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->whereRaw('is_active = ? AND deleted_at IS NULL', [1])
            ->orderBy('id')
            ->first();
    }
}
