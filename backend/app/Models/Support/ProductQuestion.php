<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Pregunta de un cliente sobre un producto.
 *
 * Tabla: `product_questions` — relación N:1 con `products` y `customers`.
 *
 * Permite a los clientes realizar consultas públicas o privadas
 * sobre productos, con posibilidad de respuesta del vendedor.
 */
class ProductQuestion extends Model
{
    //
}
