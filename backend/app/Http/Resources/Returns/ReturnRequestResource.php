<?php

namespace App\Http\Resources\Returns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una solicitud de devolución.
 *
 * Contiene la información de una solicitud realizada por el cliente
 * para devolver productos, incluyendo el pedido original, motivo
 * general, artículos a devolver y estado de la solicitud.
 */
class ReturnRequestResource extends JsonResource
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
