<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int|null $custom_design_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $discount_cents
 * @property int $subtotal_cents
 * @property string|null $product_name_snapshot
 * @property string|null $product_sku_snapshot
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = null;
        if ($this->resource->relationLoaded('order')) {
            $order = $this->resource->getRelation('order');
            if ($order !== null) {
                $order->loadMissing('currency');
                $currency = $order->currency;
            }
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'custom_design_id' => $this->custom_design_id,
            'product_name_snapshot' => $this->product_name_snapshot,
            'product_sku_snapshot' => $this->product_sku_snapshot,
            'quantity' => $this->quantity,
            'unit_price' => MoneyFormatter::payload(
                cents: $this->unit_price_cents,
                currency: $currency,
            ),
            'discount' => MoneyFormatter::payload(
                cents: $this->discount_cents,
                currency: $currency,
            ),
            'subtotal' => MoneyFormatter::payload(
                cents: $this->subtotal_cents,
                currency: $currency,
            ),
        ];
    }
}
