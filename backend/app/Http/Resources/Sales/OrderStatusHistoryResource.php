<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un registro en el historial de estados de un pedido.
 *
 * Contiene la información de un cambio de estado en un pedido,
 * incluyendo el estado anterior, nuevo estado, fecha del cambio
 * y observaciones adicionales.
 */
class OrderStatusHistoryResource extends JsonResource
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
