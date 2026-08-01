<?php

namespace App\Http\Resources\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un envío asociado a un pedido.
 *
 * Contiene la información de un envío, incluyendo transportista,
 * número de seguimiento, dirección de destino, estado del envío
 * y fecha estimada de entrega.
 */
class ShipmentResource extends JsonResource
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
