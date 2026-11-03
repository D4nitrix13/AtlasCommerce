<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de talla/capacidad de producto.
 *
 * `value` + `unit` son nullable: la taza de 11 oz los trae
 * (`value: 11, unit: 'oz'`); la talla M de ropa los deja en null.
 *
 * @mixin \App\Models\Catalog\ProductSize
 */
class AdminProductSizeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'productId' => $this->resource->product_id,
            'slug' => $this->resource->slug,
            'label' => $this->resource->label,
            'value' => $this->resource->value,
            'unit' => $this->resource->unit,
            'position' => $this->resource->position,
        ];
    }
}
