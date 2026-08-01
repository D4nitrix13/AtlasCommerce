<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un proveedor registrado.
 *
 * Contiene la información de un proveedor, incluyendo nombre comercial,
 * datos de contacto, dirección fiscal y estado de la relación comercial.
 */
class SupplierResource extends JsonResource
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
