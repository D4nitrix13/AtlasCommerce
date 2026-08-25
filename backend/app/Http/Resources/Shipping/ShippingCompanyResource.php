<?php

declare(strict_types=1);

namespace App\Http\Resources\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una empresa de transportes.
 *
 * Contiene la información de una empresa de envíos disponible,
 * incluyendo nombre, cobertura de zonas, tarifas y estado
 * de la relación comercial.
 */
class ShippingCompanyResource extends JsonResource
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
