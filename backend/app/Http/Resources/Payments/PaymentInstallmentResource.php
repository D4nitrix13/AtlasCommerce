<?php

namespace App\Http\Resources\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una cuota de pago a plazos.
 *
 * Contiene la información de una cuota individual dentro de un plan
 * de pagos fraccionados, incluyendo monto, fecha de vencimiento
 * y estado de pago.
 */
class PaymentInstallmentResource extends JsonResource
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
