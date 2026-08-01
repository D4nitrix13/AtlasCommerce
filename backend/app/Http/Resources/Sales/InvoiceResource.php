<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use App\Http\Resources\Payments\PaymentResource;
use App\Models\Sales\Invoice;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int $customer_id
 * @property int $store_id
 * @property int|null $sales_channel_id
 * @property int|null $user_issuer_id
 * @property int $currency_id
 * @property string $invoice_number
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property string $payment_status
 * @property string $invoice_status
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->resource->relationLoaded('currency')
            ? $this->resource->currency
            : null;
        $paidCents = $this->resource instanceof Invoice
            ? $this->resource->paidAmountCents()
            : 0;
        $pendingCents = $this->resource instanceof Invoice
            ? $this->resource->pendingBalanceCents()
            : ($this->total_cents - $paidCents);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'store_id' => $this->store_id,
            'currency_id' => $this->currency_id,
            'payment_status' => $this->payment_status,
            'invoice_status' => $this->invoice_status,
            'subtotal' => MoneyFormatter::payload($this->subtotal_cents, $currency),
            'discount' => MoneyFormatter::payload($this->discount_cents, $currency),
            'tax' => MoneyFormatter::payload($this->tax_cents, $currency),
            'total' => MoneyFormatter::payload($this->total_cents, $currency),
            'paid' => MoneyFormatter::payload($paidCents, $currency),
            'pending_balance' => MoneyFormatter::payload($pendingCents, $currency),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items') ?? []),
            'payments' => PaymentResource::collection($this->whenLoaded('invoicePayments') ?? []),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
        ];
    }
}
