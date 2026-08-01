<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Define un atributo de producto (ej. color, talla, material).
 *
 * Tabla: `product_attributes` — relación 1:N con `product_attribute_values`.
 *
 * Cada atributo tiene un nombre, tipo y posibles valores predefinidos.
 */
class ProductAttribute extends Model
{
    //
}
