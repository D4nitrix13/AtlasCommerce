<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $shipping_address_id
 * @property int $store_id
 * @property int|null $sales_channel_id
 * @property int|null $user_creator_id
 * @property int $currency_id
 * @property string $order_number
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $tax_cents
 * @property int $shipping_cents
 * @property int $total_cents
 * @property string $exchange_rate_snapshot
 * @property int $total_in_base_currency_cents
 * @property string $status
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->resource->relationLoaded('currency')
            ? $this->resource->currency
            : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'shipping_address_id' => $this->shipping_address_id,
            'store_id' => $this->store_id,
            'sales_channel_id' => $this->sales_channel_id,
            'currency_id' => $this->currency_id,
            'status' => $this->status,
            'subtotal' => MoneyFormatter::payload($this->subtotal_cents, $currency),
            'discount' => MoneyFormatter::payload($this->discount_cents, $currency),
            'tax' => MoneyFormatter::payload($this->tax_cents, $currency),
            'shipping' => MoneyFormatter::payload($this->shipping_cents, $currency),
            'total' => MoneyFormatter::payload($this->total_cents, $currency),
            'total_in_base_currency' => MoneyFormatter::payload(
                cents: $this->total_in_base_currency_cents,
                currency: $currency,
            ),
            'exchange_rate_snapshot' => (string) $this->exchange_rate_snapshot,
            'notes' => $this->notes,
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items') ?? []),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices') ?? []),
            'shipments' => $this->whenLoaded('shipments') ?? [],
        ];
    }
}
