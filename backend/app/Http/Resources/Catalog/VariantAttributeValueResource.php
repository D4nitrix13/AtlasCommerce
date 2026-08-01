<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar el valor de un atributo en una variante de producto.
 *
 * Contiene la relación entre una variante y un atributo específico,
 * incluyendo el valor asignado que define las características
 * de la variante.
 */
class VariantAttributeValueResource extends JsonResource
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
