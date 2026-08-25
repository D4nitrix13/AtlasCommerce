<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una promoción activa.
 *
 * Contiene la información de una campaña promocional, incluyendo nombre,
 * tipo de descuento, fechas de vigencia, reglas de elegibilidad
 * y estado de activación.
 */
class PromotionResource extends JsonResource
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
