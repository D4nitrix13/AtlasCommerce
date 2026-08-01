<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $subtotal_cents
 * @property string|null $product_name_snapshot
 * @property string|null $product_sku_snapshot
 */
class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = null;
        if ($this->resource->relationLoaded('invoice')) {
            $invoice = $this->resource->getRelation('invoice');
            if ($invoice !== null) {
                $invoice->loadMissing('currency');
                $currency = $invoice->currency;
            }
        }

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name_snapshot' => $this->product_name_snapshot,
            'product_sku_snapshot' => $this->product_sku_snapshot,
            'quantity' => $this->quantity,
            'unit_price' => MoneyFormatter::payload($this->unit_price_cents, $currency),
            'subtotal' => MoneyFormatter::payload($this->subtotal_cents, $currency),
        ];
    }
}
