<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una pregunta sobre un producto.
 *
 * Contiene la información de una consulta realizada por un cliente
 * sobre un producto, incluyendo autor, fecha de publicación,
 * respuesta oficial y estado de resolución.
 */
class ProductQuestionResource extends JsonResource
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
