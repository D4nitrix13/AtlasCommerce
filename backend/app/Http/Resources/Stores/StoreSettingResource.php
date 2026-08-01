<?php

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una configuración de tienda.
 *
 * Contiene un parámetro de configuración específico de una tienda,
 * incluyendo clave, valor y descripción de su función.
 */
class StoreSettingResource extends JsonResource
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
