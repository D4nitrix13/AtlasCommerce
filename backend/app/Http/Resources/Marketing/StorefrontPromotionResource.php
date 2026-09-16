<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público de promoción para storefront (RF-MKT-061).
 *
 * Muestra solo los campos relevantes para el visitante: nombre,
 * descripción, tipo de descuento, valor y ventana de vigencia.
 * NO expone campos admin (store_id, is_active).
 *
 * @mixin \App\Models\Marketing\Promotion
 */
class StorefrontPromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'discount_type' => $this->resource->discount_type,
            'value' => (float) $this->resource->value,
            'starts_at' => $this->resource->starts_at?->toIso8601String(),
            'ends_at' => $this->resource->ends_at?->toIso8601String(),
        ];
    }
}
