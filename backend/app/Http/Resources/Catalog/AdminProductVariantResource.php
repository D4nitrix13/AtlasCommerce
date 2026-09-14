<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de variante de producto.
 *
 * @mixin \App\Models\Catalog\ProductVariant
 */
class AdminProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'name' => $this->resource->name,
            'sku' => $this->resource->sku,
            'price_cents' => (int) $this->resource->price_cents,
            'cost_cents' => (int) $this->resource->cost_cents,
            'barcode' => $this->resource->barcode,
            'is_active' => (bool) $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'deleted_at' => $this->resource->deleted_at?->toIso8601String(),
        ];
    }
}
