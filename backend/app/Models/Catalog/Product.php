<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Producto principal del catálogo.
 *
 * Tabla: `products` — relación 1:N con `product_variants`, `product_images`, `product_attribute_values` y N:N con `stores` a través de `product_stores`.
 *
 * Entidad central del catálogo: almacena nombre, descripción,
 * precio base, SKU y estado de publicación.
 */
class Product extends Model
{
    /**
     * Tiendas donde se publica el producto.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Store::class,
            table: 'product_stores',
        )->withPivot('is_visible');
    }
}
