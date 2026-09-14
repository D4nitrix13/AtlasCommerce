<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de producto — incluye campos internos (cost_cents,
 * soft-delete metadata, is_active, published_at) que el resource
 * público omite.
 *
 * Pensado para los endpoints de /api/admin/products. NO usar en
 * storefront público (usar `ProductResource` clásico).
 *
 * @mixin \App\Models\Catalog\Product
 */
class AdminProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'sku' => $this->resource->sku,
            'description' => $this->resource->description,
            'category_id' => $this->resource->category_id,
            'brand_id' => $this->resource->brand_id,
            'currency_id' => $this->resource->currency_id,
            'base_price_cents' => (int) $this->resource->base_price_cents,
            'cost_cents' => (int) $this->resource->cost_cents,
            'is_active' => (bool) $this->resource->is_active,
            'published_at' => $this->resource->published_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'deleted_at' => $this->resource->deleted_at?->toIso8601String(),
        ];

        if ($this->resource->relationLoaded('category')) {
            $payload['category'] = $this->resource->category !== null
                ? ['id' => $this->resource->category->id, 'name' => $this->resource->category->name]
                : null;
        }

        if ($this->resource->relationLoaded('brand')) {
            $payload['brand'] = $this->resource->brand !== null
                ? ['id' => $this->resource->brand->id, 'name' => $this->resource->brand->name]
                : null;
        }

        if ($this->resource->relationLoaded('stores')) {
            $payload['stores'] = $this->resource->stores->map(function ($productStore): array {
                return [
                    'store_id' => $productStore->store_id,
                    'is_visible' => (bool) $productStore->is_visible,
                    'public_title' => $productStore->public_title,
                ];
            })->all();
        }

        return $payload;
    }
}
