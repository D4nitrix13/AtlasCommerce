<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar la relación entre un producto y una promoción.
 *
 * Contiene la información de la asignación de una promoción a un producto
 * específico, incluyendo el tipo de descuento aplicable y condiciones.
 */
class ProductPromotionResource extends JsonResource
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
