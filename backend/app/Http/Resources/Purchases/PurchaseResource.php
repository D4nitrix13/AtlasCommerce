<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una orden de compra a proveedor.
 *
 * Contiene la información de una compra realizada, incluyendo proveedor,
 * artículos adquiridos, monto total, estado de la orden y fechas relevantes.
 */
class PurchaseResource extends JsonResource
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
