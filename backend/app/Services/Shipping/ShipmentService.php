<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\ShipmentStatus;
use App\Exceptions\Shipping\CrossTenantShippingZoneException;
use App\Exceptions\Shipping\InvalidShipmentTransitionException;
use App\Models\Sales\Invoice;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShippingCompany;
use App\Models\Shipping\ShippingZone;
use Carbon\Carbon;
use RuntimeException;

/**
 * Servicio de shipments para el ciclo post-pago.
 *
 * Responsabilidades:
 *  - Crear un Shipment `pending` cuando una invoice queda liquidada.
 *  - Aplicar transiciones de estado válidas (shipped, in_transit, delivered, cancelled).
 *  - Mantener idempotencia: si ya existe un shipment activo para el order, no crea otro.
 *
 * El método `createForInvoice` se invoca dentro de la transacción del caller
 * (típicamente PaymentService) — no abre transacción propia.
 */
final class ShipmentService
{
    /**
     * Crea un shipment `pending` para la invoice liquidada.
     *
     * Idempotente: si ya existe un shipment para el order asociado a la
     * invoice (status != cancelled), retorna el existente sin crear duplicado.
     *
     * Reglas:
     *  - La invoice debe tener un order asociado.
     *  - La zona de envío (invoice.shipping_zone_id) es opcional. Si no hay,
     *    el shipment queda sin zona pero igual se crea (shipping gratis o
     *    a coordinar con el cliente).
     *  - `tracking_code` se autogenera formato `SHP-YYYYMMDD-XXXXXX`.
     *  - `shipping_cost_cents` se toma de la invoice.
     *  - Si la invoice no tiene currency_id o no tiene zona, el shipment
     *    se crea igual — esos campos quedan para edición manual.
     */
    public function createForInvoice(Invoice $invoice): Shipment
    {
        $orderId = $invoice->order_id;
        if ($orderId === null) {
            throw new RuntimeException(
                "La invoice {$invoice->id} no tiene orden asociada — no se puede crear shipment.",
            );
        }

        $existing = Shipment::query()
            ->where(column: 'order_id', operator: '=', value: $orderId)
            ->where(column: 'status', operator: '!=', value: ShipmentStatus::Cancelled->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Busca la zona ignorando el global scope: queremos resolver por ID
        // sin importar el contexto activo, y luego validar manualmente el
        // tenant para evitar referencias cross-tenant.
        $zone = $invoice->shipping_zone_id !== null
            ? ShippingZone::withoutGlobalScope('store')->find($invoice->shipping_zone_id)
            : null;

        // Defensa contra referencia cross-tenant: si la zona pertenece
        // explícitamente a otra tienda (store_id no nulo y distinto al
        // de la invoice), fail-loud. Las zonas con store_id NULL se
        // consideran globales y son válidas para cualquier invoice.
        if ($zone !== null && $zone->store_id !== null && (int) $zone->store_id !== $invoice->store_id) {
            throw CrossTenantShippingZoneException::fromZone(
                expectedStoreId: $invoice->store_id,
                actualStoreId: (int) $zone->store_id,
            );
        }

        $defaultCompany = ShippingCompany::query()
            ->orderBy('id')
            ->first();

        return Shipment::create([
            'order_id' => $orderId,
            'shipping_company_id' => $defaultCompany?->id,
            'shipping_zone_id' => $zone?->id,
            'currency_id' => $invoice->currency_id,
            'tracking_code' => $this->generateTrackingCode(),
            'shipping_cost_cents' => (int) $invoice->shipping_cents,
            'status' => ShipmentStatus::Pending,
        ]);
    }

    /**
     * Marca el shipment como despachado.
     *
     * Solo válido desde `pending`.
     */
    public function markAsShipped(Shipment $shipment, ?Carbon $shippedAt = null): Shipment
    {
        $this->transition(shipment: $shipment, to: ShipmentStatus::Shipped);

        $shipment->update([
            'shipped_at' => $shippedAt ?? now(),
        ]);

        return $shipment->fresh();
    }

    /**
     * Marca el shipment como en tránsito.
     *
     * Solo válido desde `shipped`. Estado opcional — útil cuando la
     * paquetería tiene tracking granular. Para MVP se puede saltar.
     */
    public function markAsInTransit(Shipment $shipment): Shipment
    {
        $this->transition(shipment: $shipment, to: ShipmentStatus::InTransit);

        return $shipment->fresh();
    }

    /**
     * Marca el shipment como entregado al cliente.
     *
     * Válido desde `shipped` o `in_transit`.
     */
    public function markAsDelivered(Shipment $shipment, ?Carbon $deliveredAt = null): Shipment
    {
        $this->transition(shipment: $shipment, to: ShipmentStatus::Delivered);

        $shipment->update([
            'delivered_at' => $deliveredAt ?? now(),
        ]);

        return $shipment->fresh();
    }

    /**
     * Cancela el shipment.
     *
     * Solo válido desde `pending` (no se puede cancelar un envío ya en
     * tránsito o entregado — eso es un proceso de devolución/refund,
     * fuera de scope de MVP).
     */
    public function cancel(Shipment $shipment): Shipment
    {
        $this->transition(shipment: $shipment, to: ShipmentStatus::Cancelled);

        return $shipment->fresh();
    }

    /**
     * Aplica una transición de estado validando la máquina de estados.
     *
     * @throws InvalidShipmentTransitionException
     */
    private function transition(Shipment $shipment, ShipmentStatus $to): void
    {
        $current = $shipment->status;

        if (! $current->canTransitionTo($to)) {
            throw InvalidShipmentTransitionException::fromTo(from: $current, to: $to);
        }

        $shipment->update(['status' => $to]);
    }

    /**
     * Genera un código de tracking único formato `SHP-YYYYMMDD-XXXXXX`.
     *
     * Usa `random_int` para el sufijo (no `uniqid` por colisiones en
     * concurrencia). Bajo carga real debería usarse un sequence.
     */
    private function generateTrackingCode(): string
    {
        $date = now()->format('Ymd');

        do {
            $suffix = strtoupper(base_convert((string) random_int(0, 36 ** 6 - 1), 10, 36));
            $code = sprintf('SHP-%s-%s', $date, $suffix);
        } while (Shipment::withTrashed()
            ->where(column: 'tracking_code', operator: '=', value: $code)
            ->exists());

        return $code;
    }
}
