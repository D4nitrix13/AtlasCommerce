<?php

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un ticket de soporte al cliente.
 *
 * Contiene la información de un ticket de atención, incluyendo
 * asunto, categoría, prioridad, estado, cliente asignado
 * y fechas de apertura y resolución.
 */
class SupportTicketResource extends JsonResource
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
