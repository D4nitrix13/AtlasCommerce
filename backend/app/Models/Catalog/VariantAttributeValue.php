<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla pivote que asocia variantes con valores de atributo.
 *
 * Tabla: `variant_attribute_values` — relación N:1 con `product_variants` y `product_attribute_values`.
 *
 * Define qué valores de atributo posee cada variante del producto.
 */
class VariantAttributeValue extends Model
{
    //
}
