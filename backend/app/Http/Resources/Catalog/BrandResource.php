<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar una marca de producto.
 *
 * Contiene la información de la marca, incluyendo nombre,
 * logo, descripción y estado de activación.
 */
class BrandResource extends JsonResource
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
