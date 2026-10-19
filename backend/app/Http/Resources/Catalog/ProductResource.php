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
 * Las colecciones de contenido extendido (highlights, groupedSpecs,
 * faqs, extendedDescriptions) sólo se incluyen si las relaciones
 * fueron eager-loaded en el query. Esto permite que el resource
 * siga siendo liviano para usos que no las necesitan (carrito,
 * returns) y a la vez exponga el shape completo cuando el
 * storefront PDP pide el product detail.
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

        $payload = [
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

        if ($this->resource->relationLoaded('highlights')) {
            $payload['highlights'] = $this->resource->highlights
                ->map(fn ($highlight): array => [
                    'icon' => $highlight->icon,
                    'label' => $highlight->label,
                ])
                ->all();
        }

        if ($this->resource->relationLoaded('groupedSpecs')) {
            $payload['groupedSpecs'] = $this->resource->groupedSpecs
                ->map(fn ($group): array => [
                    'category' => $group->category,
                    'items' => $group->values
                        ->map(fn ($value): array => [
                            'label' => $value->label,
                            'value' => $value->value,
                        ])
                        ->all(),
                ])
                ->all();
        }

        if ($this->resource->relationLoaded('faqs')) {
            $payload['faqs'] = $this->resource->faqs
                ->map(fn ($faq): array => [
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ])
                ->all();
        }

        if ($this->resource->relationLoaded('extendedDescriptions')) {
            $payload['extendedDescriptions'] = $this->resource->extendedDescriptions
                ->map(fn ($entry): array => [
                    'title' => $entry->title,
                    'body' => $entry->body,
                ])
                ->all();
        }

        return $payload;
    }
}
