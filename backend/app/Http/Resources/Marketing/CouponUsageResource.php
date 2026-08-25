<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar el registro de uso de un cupón.
 *
 * Contiene la información de cada vez que un cupón ha sido aplicado,
 * incluyendo el usuario que lo utilizó, el pedido asociado
 * y el monto descontado.
 */
class CouponUsageResource extends JsonResource
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
