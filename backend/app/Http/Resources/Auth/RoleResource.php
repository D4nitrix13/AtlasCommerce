<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un rol del sistema de autenticación.
 *
 * Contiene la información de un rol asignable a usuarios,
 * incluyendo su nombre, permisos asociados y nivel de acceso.
 */
class RoleResource extends JsonResource
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
