<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un producto del catálogo.
 *
 * Contiene la información completa de un producto, incluyendo nombre,
 * descripción, precio, marca, categorías, imágenes y variantes disponibles.
 */
class ProductResource extends JsonResource
{
    /**
     * Transforma el resource en un array para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray(request: $request);
    }
}
