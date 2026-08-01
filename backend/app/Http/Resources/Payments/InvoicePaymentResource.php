<?php

namespace App\Http\Resources\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar el pago asociado a una factura.
 *
 * Contiene la información de un pago registrado contra una factura,
 * incluyendo monto, método de pago utilizado y estado de la transacción.
 */
class InvoicePaymentResource extends JsonResource
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
