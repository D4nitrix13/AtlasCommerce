<?php

namespace App\Models\Taxes;

use Illuminate\Database\Eloquent\Model;

/**
 * Definición de un impuesto del sistema.
 *
 * Tabla: `taxes` — relación 1:N con `product_tax`.
 *
 * Define los impuestos configurados: nombre, tasa porcentual,
 * código fiscal y estado de habilitación.
 */
class Tax extends Model
{
    //
}
