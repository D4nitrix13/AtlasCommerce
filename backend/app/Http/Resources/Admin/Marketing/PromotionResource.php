<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de promoción (RF-MKT-061).
 *
 * @mixin \App\Models\Marketing\Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'discount_type' => $this->resource->discount_type,
            'value' => (float) $this->resource->value,
            'starts_at' => $this->resource->starts_at?->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),
            'is_active' => (bool) $this->resource->is_active,
            'is_active_now' => $this->resource->isActive(),
            'store_id' => $this->resource->store_id,
            'products' => $this->whenLoaded('products', fn () => $this->resource->products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])->all()),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
