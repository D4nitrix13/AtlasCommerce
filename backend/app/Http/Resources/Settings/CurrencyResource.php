<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una moneda disponible en el sistema.
 *
 * Contiene la información de una moneda, incluyendo código ISO,
 * símbolo, nombre y precisión decimal para manejo de precios.
 */
class CurrencyResource extends JsonResource
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
