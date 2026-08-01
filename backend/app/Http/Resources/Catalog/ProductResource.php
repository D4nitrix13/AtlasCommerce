<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público de producto.
 *
 * No expone `cost_cents` ni datos internos del inventario. Diseñado
 * para el storefront y para el carrito/checkout.
 *
 * @property int $id
 * @property int $category_id
 * @property int|null $brand_id
 * @property int|null $currency_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $sku
 * @property int $base_price_cents
 * @property int $cost_cents
 * @property bool $is_active
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->resource->relationLoaded('currency')
            ? $this->resource->currency
            : null;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'currency_id' => $this->currency_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,
            'is_active' => $this->is_active,
            'price' => MoneyFormatter::payload(
                cents: $this->base_price_cents,
                currency: $currency,
            ),
        ];
    }
}
