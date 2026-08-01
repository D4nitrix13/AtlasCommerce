<?php

namespace App\Http\Resources\Carts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo en la lista de deseos.
 *
 * Contiene la información de un producto guardado por el usuario
 * en su lista de deseos, incluyendo fecha de agregado y precio
 * registrado al momento de la consulta.
 */
class WishlistItemResource extends JsonResource
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
