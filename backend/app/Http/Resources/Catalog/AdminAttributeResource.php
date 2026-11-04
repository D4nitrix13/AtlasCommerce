<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de atributo.
 *
 * @mixin \App\Models\Catalog\ProductAttribute
 */
class AdminAttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'type' => $this->resource->type,
            // Unidad de medida ('oz', 'lb', 'ml', 'kg', 'cm') o null
            // cuando el atributo no se mide.
            'unit' => $this->resource->unit,
            // Opciones válidas cuando `type = 'select'` (JSON array).
            'options' => $this->resource->options,
            // Categorías donde aplica el atributo (JSON array de slugs).
            'applies_to' => $this->resource->applies_to,
            'description' => $this->resource->description,
            'is_filterable' => (bool) $this->resource->is_filterable,
            'is_required' => (bool) $this->resource->is_required,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
