<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una imagen de producto.
 *
 * Contiene la información de una imagen asociada a un producto,
 * incluyendo URL, texto alternativo, orden de visualización
 * y si es imagen principal.
 */
class ProductImageResource extends JsonResource
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
