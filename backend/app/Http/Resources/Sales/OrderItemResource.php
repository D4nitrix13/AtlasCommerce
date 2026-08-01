<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo dentro de un pedido.
 *
 * Contiene la información de un producto incluido en un pedido,
 * incluyendo variante, cantidad, precio unitario, impuestos
 * y subtotal de línea.
 */
class OrderItemResource extends JsonResource
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
