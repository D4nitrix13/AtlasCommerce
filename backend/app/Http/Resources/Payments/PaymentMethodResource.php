<?php

namespace App\Http\Resources\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un método de pago disponible.
 *
 * Contiene la información de un método de pago configurado en el sistema,
 * como tarjeta de crédito, transferencia bancaria o billetera digital,
 * incluyendo su estado y configuración.
 */
class PaymentMethodResource extends JsonResource
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
