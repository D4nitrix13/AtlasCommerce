<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $payment_method_id
 * @property int $store_id
 * @property int $currency_id
 * @property int $amount_cents
 * @property string $exchange_rate_snapshot
 * @property string|null $reference
 * @property string $status
 */
class PaymentResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'payment_method_id' => $this->payment_method_id,
            'store_id' => $this->store_id,
            'currency_id' => $this->currency_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'amount' => MoneyFormatter::payload(
                cents: $this->amount_cents,
                currency: $currency,
            ),
            'applied_amount' => $this->pivot?->applied_amount_cents !== null
                ? MoneyFormatter::payload(
                    cents: (int) $this->pivot->applied_amount_cents,
                    currency: $currency,
                )
                : null,
        ];
    }
}
