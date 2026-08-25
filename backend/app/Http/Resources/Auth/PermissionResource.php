<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un permiso del sistema.
 *
 * Contiene la información de un permiso asignable a roles,
 * incluyendo su nombre, identificador y descripción.
 */
class PermissionResource extends JsonResource
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
