<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de stock de inventario.
 *
 * @mixin \App\Models\Inventory\Inventory
 */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'warehouse_id' => $this->resource->warehouse_id,
            'product_id' => $this->resource->product_id,
            'product_variant_id' => $this->resource->product_variant_id,
            'current_stock' => (int) $this->resource->current_stock,
            'minimum_stock' => (int) $this->resource->minimum_stock,
            'reserved_stock' => (int) $this->resource->reserved_stock,
            'available_stock' => $this->resource->getAvailableStockAttribute(),
            'is_low' => $this->resource->isLow(),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->resource->product->id,
                'name' => $this->resource->product->name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->resource->warehouse->id,
                'name' => $this->resource->warehouse->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
