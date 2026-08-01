<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

/**
 * Asociación entre una promoción y un producto.
 *
 * Tabla: `product_promotions` — relación N:1 con `promotions` y `products`.
 *
 * Tabla pivote que vincula promociones con productos específicos.
 */
class ProductPromotion extends Model
{
    //
}
