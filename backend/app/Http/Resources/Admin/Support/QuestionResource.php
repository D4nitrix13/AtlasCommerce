<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de pregunta sobre producto (RF-SUP-162).
 *
 * @mixin \App\Models\Support\ProductQuestion
 */
class QuestionResource extends JsonResource
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
            'question' => $this->resource->question,
            'answer' => $this->resource->answer,
            'asked_at' => $this->resource->asked_at?->toIso8601String(),
            'answered_at' => $this->resource->answered_at?->toIso8601String(),
            'is_answered' => $this->resource->answer !== null,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->resource->product->id,
                'name' => $this->resource->product->name,
            ]),
        ];
    }
}
