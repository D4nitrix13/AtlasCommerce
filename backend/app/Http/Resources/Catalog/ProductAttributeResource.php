<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un atributo de producto.
 *
 * Contiene la definición de un atributoutilizable en productos,
 * como color, talla o material, incluyendo su tipo de valor
 * y opciones disponibles.
 */
class ProductAttributeResource extends JsonResource
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
