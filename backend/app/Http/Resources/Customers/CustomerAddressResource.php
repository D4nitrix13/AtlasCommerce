<?php

declare(strict_types=1);

namespace App\Http\Resources\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una dirección de envío o facturación de un cliente.
 *
 * Contiene la información de una dirección registrada, incluyendo
 * nombre receptor, línea de dirección, ciudad, código postal y país.
 */
class CustomerAddressResource extends JsonResource
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
