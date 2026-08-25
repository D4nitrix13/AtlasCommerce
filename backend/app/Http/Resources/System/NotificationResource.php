<?php

declare(strict_types=1);

namespace App\Http\Resources\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una notificación del sistema.
 *
 * Contiene la información de una notificación enviada a un usuario,
 * incluyendo título, mensaje, tipo, estado de lectura y fecha
 * de creación.
 */
class NotificationResource extends JsonResource
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
