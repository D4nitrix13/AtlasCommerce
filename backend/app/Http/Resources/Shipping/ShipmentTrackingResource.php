<?php

namespace App\Http\Resources\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un evento en el seguimiento de un envío.
 *
 * Contiene la información de un evento de rastreo, incluyendo estado,
 * ubicación, fecha del evento y descripción de la actividad registrada
 * por el transportista.
 */
class ShipmentTrackingResource extends JsonResource
{
    /**
     * Transforma el resource en un array para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
