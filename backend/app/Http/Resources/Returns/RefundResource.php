<?php

declare(strict_types=1);

namespace App\Http\Resources\Returns;

use App\Http\Resources\Payments\PaymentMethodResource;
use App\Http\Resources\Settings\CurrencyResource;
use App\Support\MoneyFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un reembolso procesado.
 *
 * @property int $id
 * @property int $return_request_id
 * @property int|null $payment_method_id
 * @property int $currency_id
 * @property int $amount_cents
 * @property string $exchange_rate_snapshot
 * @property string $status
 * @property Carbon|null $refunded_at
 */
class RefundResource extends JsonResource
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
            'return_request_id' => $this->return_request_id,
            'payment_method_id' => $this->payment_method_id,
            'currency_id' => $this->currency_id,
            'status' => $this->status,
            'amount' => MoneyFormatter::payload(
                cents: $this->amount_cents,
                currency: $currency,
            ),
            'exchange_rate_snapshot' => (string) $this->exchange_rate_snapshot,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'return_request' => new ReturnRequestResource($this->whenLoaded('returnRequest')),
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
        ];
    }
}
