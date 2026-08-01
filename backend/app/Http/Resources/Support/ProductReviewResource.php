<?php

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una reseña de un producto.
 *
 * Contiene la información de una reseña dejada por un cliente,
 * incluyendo calificación, comentario, autor y fecha de publicación.
 */
class ProductReviewResource extends JsonResource
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
