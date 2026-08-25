<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar el detalle de una transacción de pago.
 *
 * Contiene la información técnica de una transacción procesada
 * por la pasarela de pagos, incluyendo código de respuesta,
 * mensaje de la pasarela y datos delWebResponse.
 */
class PaymentTransactionResource extends JsonResource
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
