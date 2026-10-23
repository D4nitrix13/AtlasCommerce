<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de variante de producto.
 *
 * Expone también los campos de acabado, condición y stock
 * (`finish_*`, `condition`, `stock`, `handedness`) que la API admin
 * acepta desde la slice 1.4: sin ellos el panel no puede editar lo que
 * la API valida, y el `condition` del alta (`nuevo` por default) es
 * invisible.
 *
 * `size_id` NO se expone: la API admin todavía no lo escribe (queda
 * fuera de las reglas del FormRequest), así que publicarlo sería
 * prometer un campo que el admin no puede modificar.
 *
 * @mixin \App\Models\Catalog\ProductVariant
 */
class AdminProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'name' => $this->resource->name,
            'sku' => $this->resource->sku,
            'price_cents' => (int) $this->resource->price_cents,
            'cost_cents' => (int) $this->resource->cost_cents,
            'barcode' => $this->resource->barcode,
            'is_active' => (bool) $this->resource->is_active,
            'finish_slug' => $this->resource->finish_slug,
            'finish_label' => $this->resource->finish_label,
            'finish_hex' => $this->resource->finish_hex,
            'condition' => (string) $this->resource->condition,
            'stock' => (int) $this->resource->stock,
            'handedness' => $this->resource->handedness,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'deleted_at' => $this->resource->deleted_at?->toIso8601String(),
        ];
    }
}
