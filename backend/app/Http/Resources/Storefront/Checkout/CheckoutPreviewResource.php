<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront\Checkout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de preview del checkout (totales calculados sin crear orden).
 */
class CheckoutPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cart_id' => $this->resource['cart_id'] ?? null,
            'currency_code' => $this->resource['currency_code'] ?? null,
            'subtotal_cents' => (int) ($this->resource['subtotal_cents'] ?? 0),
            'discount_cents' => (int) ($this->resource['discount_cents'] ?? 0),
            'tax_cents' => (int) ($this->resource['tax_cents'] ?? 0),
            'shipping_cents' => (int) ($this->resource['shipping_cents'] ?? 0),
            'total_cents' => (int) ($this->resource['total_cents'] ?? 0),
            'items_count' => (int) ($this->resource['items_count'] ?? 0),
        ];
    }
}
