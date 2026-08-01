<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un movimiento de inventario.
 *
 * Contiene la información de una transacción de stock, incluyendo
 * el tipo de movimiento (entrada, salida, ajuste), cantidad,
 * referencia del documento y fecha de ejecución.
 */
class InventoryMovementResource extends JsonResource
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
