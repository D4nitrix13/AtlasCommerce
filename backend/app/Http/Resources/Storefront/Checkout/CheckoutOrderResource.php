<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront\Checkout;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de orden creada por el checkout.
 *
 * @mixin \App\Models\Sales\Order
 */
class CheckoutOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->resource->id,
            'order_number' => $this->resource->order_number ?? null,
            'status' => $this->resource->status instanceof BackedEnum
                ? $this->resource->status->value
                : $this->resource->status,
            'currency_code' => $this->resource->currency?->code,
            'subtotal_cents' => (int) ($this->resource->subtotal_cents ?? 0),
            'discount_cents' => (int) ($this->resource->discount_cents ?? 0),
            'tax_cents' => (int) ($this->resource->tax_cents ?? 0),
            'shipping_cents' => (int) ($this->resource->shipping_cents ?? 0),
            'total_cents' => (int) ($this->resource->total_cents ?? 0),
            'placed_at' => $this->resource->ordered_at?->toIso8601String(),
        ];
    }
}
