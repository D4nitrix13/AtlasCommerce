<?php

declare(strict_types=1);

namespace App\Http\Resources\Shipping;

use App\Http\Resources\Settings\CurrencyResource;
use App\Support\MoneyFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un envío.
 *
 * Shape:
 *  - id, order_id, status (string del enum), tracking_code
 *  - shipping_cost (MoneyFormatter: cents + formatted + currency_code)
 *  - shipping_company (whenLoaded)
 *  - shipping_zone (whenLoaded)
 *  - currency (whenLoaded)
 *  - timestamps ISO 8601
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $shipping_company_id
 * @property int|null $shipping_zone_id
 * @property int $currency_id
 * @property string|null $tracking_code
 * @property int $shipping_cost_cents
 * @property string $status
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 */
class ShipmentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'shipping_company_id' => $this->shipping_company_id,
            'shipping_zone_id' => $this->shipping_zone_id,
            'currency_id' => $this->currency_id,
            'tracking_code' => $this->tracking_code,
            'status' => $this->status,
            'shipping_cost' => MoneyFormatter::payload(
                cents: $this->shipping_cost_cents,
                currency: $currency,
            ),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'shipping_company' => new ShippingCompanyResource($this->whenLoaded('shippingCompany')),
            'shipping_zone' => new ShippingZoneResource($this->whenLoaded('shippingZone')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
        ];
    }
}
