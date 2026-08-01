<?php

namespace App\Http\Resources\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un registro de auditoría del sistema.
 *
 * Contiene la información de una acción registrada en el log de auditoría,
 * incluyendo usuario que realizó la acción, tipo de evento,
 * datos afectados y marca de tiempo.
 */
class AuditResource extends JsonResource
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
