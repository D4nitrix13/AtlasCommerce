<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketing;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de cupón (RF-MKT-060).
 *
 * Shape controlado: expone todos los campos que el admin necesita
 * para mantener el cupón (vigencia, valor, mínimos, límites).
 *
 * @mixin \App\Models\Marketing\Coupon
 */
class AdminCouponResource extends JsonResource
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
            'code' => $this->resource->code,
            'discount_type' => $this->resource->discount_type,
            'value' => (float) $this->resource->value,
            'minimum_amount' => MoneyFormatter::payload(
                cents: (int) round((float) $this->resource->minimum_amount * 100),
                currency: $currency,
            ),
            'starts_at' => $this->resource->starts_at?->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),
            'usage_limit' => $this->resource->usage_limit,
            'used_count' => (int) $this->resource->used_count,
            'is_active' => (bool) $this->resource->is_active,
            'store_id' => $this->resource->store_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
