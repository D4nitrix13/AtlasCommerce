<?php

declare(strict_types=1);

namespace App\Http\Resources\Returns;

use App\Http\Resources\Catalog\ProductResource;
use App\Http\Resources\Catalog\ProductVariantResource;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un item dentro de una solicitud de devolución.
 *
 * @property int $id
 * @property int $return_request_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $amount_cents
 */
class ReturnRequestItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_request_id' => $this->return_request_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'amount' => MoneyFormatter::payload(
                cents: $this->amount_cents,
                currency: null,
            ),
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_variant' => new ProductVariantResource($this->whenLoaded('productVariant')),
        ];
    }
}
