<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de precio mayorista (RF-CAT-029).
 *
 * Expone la configuración del tier mayorista y el cálculo del precio
 * unitario (vía `WholesalePricingService`) para preview rápido desde
 * el panel admin.
 *
 * @mixin \App\Models\Catalog\WholesalePrice
 */
class AdminWholesalePriceResource extends JsonResource
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
            'product_id' => $this->resource->product_id,
            'store_id' => $this->resource->store_id,
            'min_qty' => (int) $this->resource->min_qty,
            'price' => MoneyFormatter::payload(cents: $this->resource->price_cents, currency: $currency),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
