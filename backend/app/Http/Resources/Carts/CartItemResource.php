<?php

namespace App\Http\Resources\Carts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo dentro del carrito de compras.
 *
 * Contiene la información de un producto añadido al carrito,
 * incluyendo cantidad, variante seleccionada y precio unitario.
 */
class CartItemResource extends JsonResource
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
