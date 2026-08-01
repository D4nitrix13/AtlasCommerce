<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Artículo individual dentro de un pedido.
 *
 * Tabla: `order_items` — relación N:1 con `orders`.
 *
 * Referencia al producto, variante, cantidad,
 * precio unitario y subtotal de la línea.
 */
class OrderItem extends Model
{
    //
}
