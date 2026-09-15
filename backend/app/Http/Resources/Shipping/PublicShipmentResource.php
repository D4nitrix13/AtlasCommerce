<?php

declare(strict_types=1);

namespace App\Http\Resources\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público de shipment para tracking (RF-SHIP-083).
 *
 * Diseñado para no exponer datos sensibles (no devuelve info del
 * customer, sólo del envío y resumen de la orden).
 *
 * @mixin \App\Models\Shipping\Shipment
 */
class PublicShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shipment = $this->resource;

        return [
            'id' => $shipment->id,
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->status,
            'status_label' => $this->humanStatus($shipment->status->value),
            'shipping_company' => $shipment->relationLoaded('shippingCompany')
                ? $shipment->shippingCompany?->name
                : null,
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'estimated_delivery_at' => null, // Pendiente integración con courier API.
            'order' => [
                'number' => $shipment->relationLoaded('order')
                    ? $shipment->order?->order_number
                    : null,
                'items_count' => $shipment->relationLoaded('order')
                    ? $shipment->order?->items?->count()
                    : null,
            ],
            'currency' => $shipment->relationLoaded('currency')
                ? $shipment->currency?->code
                : null,
        ];
    }

    /**
     * Mapea el status enum a etiqueta legible en español.
     */
    private function humanStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente de despacho',
            'shipped' => 'En camino',
            'in_transit' => 'En tránsito',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }
}
