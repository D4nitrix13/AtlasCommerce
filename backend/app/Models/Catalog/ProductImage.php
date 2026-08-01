<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Imagen asociada a un producto.
 *
 * Tabla: `product_images` — relación N:1 con `products`.
 *
 * Almacena la URL, texto alternativo y orden de visualización
 * de cada imagen del producto.
 */
class ProductImage extends Model
{
    //
}
