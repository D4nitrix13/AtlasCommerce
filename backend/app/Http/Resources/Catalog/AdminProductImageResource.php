<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de imagen de producto.
 *
 * @mixin \App\Models\Catalog\ProductImage
 */
class AdminProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'image_url' => $this->resource->image_url,
            'alt_text' => $this->resource->alt_text,
            'sort_order' => (int) $this->resource->sort_order,
            'is_primary' => (bool) $this->resource->is_primary,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
