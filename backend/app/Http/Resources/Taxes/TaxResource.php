<?php

declare(strict_types=1);

namespace App\Http\Resources\Taxes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un impuesto configurado en el sistema.
 *
 * Contiene la información de un impuesto, incluyendo nombre, código,
 * tasa porcentual, jurisdicción aplicable y estado de activación.
 */
class TaxResource extends JsonResource
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
