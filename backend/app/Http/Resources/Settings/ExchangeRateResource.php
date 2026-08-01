<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una tasa de cambio entre monedas.
 *
 * Contiene la información de una tasa de cambio, incluyendo
 * moneda origen, moneda destino, valor de conversión y fecha
 * de la última actualización.
 */
class ExchangeRateResource extends JsonResource
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
