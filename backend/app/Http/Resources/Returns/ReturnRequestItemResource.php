<?php

namespace App\Http\Resources\Returns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo dentro de una solicitud de devolución.
 *
 * Contiene la información de un producto específico que el cliente
 * desea devolver, incluyendo motivo, cantidad y estado de revisión.
 */
class ReturnRequestItemResource extends JsonResource
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
