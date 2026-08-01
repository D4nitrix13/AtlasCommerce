<?php

namespace App\Http\Resources\Returns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un reembolso procesado.
 *
 * Contiene la información de un reembolso emitido al cliente,
 * incluyendo monto, método de reembolso, motivo y estado
 * del proceso de devolución.
 */
class RefundResource extends JsonResource
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
