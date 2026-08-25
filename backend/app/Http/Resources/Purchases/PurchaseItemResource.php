<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo dentro de una orden de compra.
 *
 * Contiene la información de un producto adquirido a un proveedor,
 * incluyendo cantidad, precio unitario de compra y subtotal.
 */
class PurchaseItemResource extends JsonResource
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
