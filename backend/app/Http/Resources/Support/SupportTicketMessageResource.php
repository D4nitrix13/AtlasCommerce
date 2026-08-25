<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un mensaje dentro de un ticket de soporte.
 *
 * Contiene la información de un mensaje intercambiado entre el cliente
 * y el equipo de soporte, incluyendo autor, contenido y fecha de envío.
 */
class SupportTicketMessageResource extends JsonResource
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
