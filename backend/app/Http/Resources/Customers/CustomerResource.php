<?php

namespace App\Http\Resources\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un cliente registrado en la plataforma.
 *
 * Contiene la información del perfil del cliente, incluyendo datos
 * personales, historial de compras y estado de la cuenta.
 */
class CustomerResource extends JsonResource
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
