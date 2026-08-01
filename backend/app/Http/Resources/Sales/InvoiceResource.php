<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una factura emitida.
 *
 * Contiene la información de una factura comercial, incluyendo
 * número de factura, pedido asociado, impuestos, total y estado
 * de pago.
 */
class InvoiceResource extends JsonResource
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
