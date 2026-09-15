<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de promoción (RF-MKT-061).
 *
 * @mixin \App\Models\Marketing\Promotion
 */
class AdminPromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->resource->relationLoaded('store')
            ? $this->resource->store?->relationLoaded('currency')
                ? $this->resource->store->currency
                : null
            : null;

        return [
            'id' => $this->resource->id,
            'store_id' => $this->resource->store_id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'discount_type' => $this->resource->discount_type,
            'value' => (float) $this->resource->value,
            'starts_at' => $this->resource->starts_at?->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),
            'is_active' => (bool) $this->resource->is_active,
            'product_ids' => $this->resource->relationLoaded('products')
                ? $this->resource->products->pluck('id')->all()
                : null,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
