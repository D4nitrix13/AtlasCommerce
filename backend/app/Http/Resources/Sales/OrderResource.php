<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un pedido del cliente.
 *
 * Contiene la información completa de un pedido, incluyendo cliente,
 * artículos, dirección de envío, estado, totales y fechas relevantes
 * del ciclo de vida del pedido.
 */
class OrderResource extends JsonResource
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
