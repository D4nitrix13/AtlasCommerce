<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de tienda (RF-STOR-132).
 *
 * Expone campos que el admin necesita configurar. `currency_id` y `slug`
 * son legibles pero NO modificables vía PUT (slug es estable, currency
 * implica migraciones de datos).
 *
 * @mixin \App\Models\Stores\Store
 */
class AdminStoreResource extends JsonResource
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
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'address' => $this->resource->address,
            'description' => $this->resource->description,
            'primary_color' => $this->resource->primary_color,
            'secondary_color' => $this->resource->secondary_color,
            'tax_rate' => (float) $this->resource->tax_rate,
            'maintenance_mode' => (bool) $this->resource->maintenance_mode,
            'is_active' => (bool) $this->resource->is_active,
            'currency_id' => $this->resource->currency_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
