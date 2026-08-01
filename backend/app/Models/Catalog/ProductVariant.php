<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Variante específica de un producto (ej. talla, color combinado).
 *
 * Tabla: `product_variants` — relación N:1 con `products`.
 *
 * Cada variante puede tener su propio SKU, precio y nivel de stock.
 */
class ProductVariant extends Model
{
    //
}
