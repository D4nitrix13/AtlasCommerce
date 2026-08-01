<?php

namespace App\Http\Resources\Carts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar la lista de deseos de un usuario.
 *
 * Contiene el conjunto de productos que el usuario ha guardado
 * para compra futura, incluyendo el total de artículos almacenados.
 */
class WishlistResource extends JsonResource
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
