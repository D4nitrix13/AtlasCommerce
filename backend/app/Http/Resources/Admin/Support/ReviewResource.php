<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de reseña de producto (RF-SUP-161).
 *
 * @mixin \App\Models\Support\ProductReview
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'customer_id' => $this->resource->customer_id,
            'store_id' => $this->resource->store_id,
            'rating' => (int) $this->resource->rating,
            'comment' => $this->resource->comment,
            'is_approved' => (bool) $this->resource->is_approved,
            'reviewed_at' => $this->resource->reviewed_at?->toIso8601String(),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->resource->product->id,
                'name' => $this->resource->product->name,
            ]),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
