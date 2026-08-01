<?php

namespace App\Models\Taxes;

use Illuminate\Database\Eloquent\Model;

/**
 * Impuesto aplicable a productos.
 *
 * Tabla: `product_tax` — relación N:1 con `products` y `taxes`.
 *
 * Tabla pivote que asocia productos con impuestos,
 * definiendo la tasa aplicable a cada producto.
 */
class ProductTax extends Model
{
    //
}
