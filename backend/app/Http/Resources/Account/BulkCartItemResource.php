<?php

declare(strict_types=1);

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de item del bulk-cart.
 *
 * @mixin \App\Models\Account\BulkCartItem
 */
class BulkCartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'customer_id' => $this->resource->customer_id,
            'product_id' => $this->resource->product_id,
            'product_variant_id' => $this->resource->product_variant_id,
            'quantity' => (int) $this->resource->quantity,
            'notes' => $this->resource->notes,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->resource->product->id,
                'name' => $this->resource->product->name,
                'sku' => $this->resource->product->sku ?? null,
            ]),
            'product_variant' => $this->whenLoaded('productVariant', fn () => [
                'id' => $this->resource->productVariant->id,
                'sku' => $this->resource->productVariant->sku ?? null,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
