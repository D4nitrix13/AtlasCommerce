<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una categoría de productos.
 *
 * Contiene la información de la categoría, incluyendo nombre,
 * descripción, imagen, categoría padre y posición en el árbol
 * de navegación.
 */
class CategoryResource extends JsonResource
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
