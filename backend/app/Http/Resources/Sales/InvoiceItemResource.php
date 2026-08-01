<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo dentro de una factura.
 *
 * Contiene la información de un producto facturado, incluyendo
 * descripción, cantidad, precio unitario y subtotal de línea.
 */
class InvoiceItemResource extends JsonResource
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
