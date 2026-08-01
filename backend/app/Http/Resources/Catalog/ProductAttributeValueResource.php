<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar el valor de un atributo asignado a un producto.
 *
 * Contiene la relación entre un producto y un atributo específico,
 * incluyendo el valor asignado y su posición de visualización.
 */
class ProductAttributeValueResource extends JsonResource
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
