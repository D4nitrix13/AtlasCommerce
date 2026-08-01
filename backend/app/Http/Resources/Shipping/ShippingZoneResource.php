<?php

namespace App\Http\Resources\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una zona de envío configurada.
 *
 * Contiene la información de una zona geográfica con reglas de envío
 * específicas, incluyendo países, estados o códigos postales cubiertos
 * y las tarifas asociadas.
 */
class ShippingZoneResource extends JsonResource
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
