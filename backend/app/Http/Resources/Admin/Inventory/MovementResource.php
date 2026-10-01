<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de movimiento de inventario.
 *
 * @mixin \App\Models\Inventory\InventoryMovement
 */
class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'inventory_id' => $this->resource->inventory_id,
            'user_id' => $this->resource->user_id,
            'movement_type' => $this->resource->movement_type,
            'quantity' => (int) $this->resource->quantity,
            'previous_stock' => (int) $this->resource->previous_stock,
            'new_stock' => (int) $this->resource->new_stock,
            'reason' => $this->resource->reason,
            'movement_date' => $this->resource->movement_date?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'product' => $this->whenLoaded(
                'inventory.product',
                fn () => [
                    'id' => $this->resource->inventory?->product?->id,
                    'name' => $this->resource->inventory?->product?->name,
                ],
            ),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->resource->user?->id,
                'email' => $this->resource->user?->email,
            ]),
        ];
    }
}
