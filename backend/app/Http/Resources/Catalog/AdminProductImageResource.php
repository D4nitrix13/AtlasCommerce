<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource admin de imagen de producto.
 *
 * @mixin \App\Models\Catalog\ProductImage
 */
class AdminProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            // Variante (acabado) a la que pertenece la foto. `null` es un
            // valor legítimo y frecuente (foto genérica del producto), así
            // que se castea a `int|null` explícito: el panel admin necesita
            // distinguir "sin variante" de "con variante 0".
            'variant_id' => $this->resource->variant_id === null ? null : (int) $this->resource->variant_id,
            'image_url' => $this->resource->image_url,
            'alt_text' => $this->resource->alt_text,
            'sort_order' => (int) $this->resource->sort_order,
            'is_primary' => (bool) $this->resource->is_primary,
            // El ancho es parte de la identidad de la fila desde que el
            // seeder guarda las 4 resoluciones de una misma foto. Sin
            // exponerlo, el admin que sube un archivo recibe N filas que
            // sólo se distinguen por `image_url` y no puede mapearlas a
            // los tamaños que le devolvió la respuesta.
            'width' => $this->resource->width,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
