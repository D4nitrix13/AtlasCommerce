<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de cupón (RF-MKT-060).
 *
 * @mixin \App\Models\Marketing\Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'discount_type' => $this->resource->discount_type,
            'value' => (float) $this->resource->value,
            'minimum_amount' => (float) $this->resource->minimum_amount,
            'starts_at' => $this->resource->starts_at?->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),
            'usage_limit' => $this->resource->usage_limit,
            'used_count' => (int) $this->resource->used_count,
            'is_active' => (bool) $this->resource->is_active,
            'is_valid_now' => $this->resource->isValid(),
            'store_id' => $this->resource->store_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
