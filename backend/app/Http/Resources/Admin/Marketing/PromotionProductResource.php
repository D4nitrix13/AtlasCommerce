<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de asociación promotion↔product (pivote product_promotion).
 *
 * @mixin \App\Models\Marketing\ProductPromotion
 */
class PromotionProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'promotion_id' => $this->resource->promotion_id,
            'product_id' => $this->resource->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->resource->product->id,
                'name' => $this->resource->product->name,
            ]),
            'promotion' => $this->whenLoaded('promotion', fn () => [
                'id' => $this->resource->promotion->id,
                'name' => $this->resource->promotion->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
