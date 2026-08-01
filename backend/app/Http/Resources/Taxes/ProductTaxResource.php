<?php

namespace App\Http\Resources\Taxes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar la relación entre un producto y un impuesto.
 *
 * Contiene la información de la asignación de un impuesto a un producto
 * específico, incluyendo el tipo de impuesto, tasa aplicable
 * y si es obligatorio o configurable.
 */
class ProductTaxResource extends JsonResource
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
