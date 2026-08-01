<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Pedido realizado por un cliente.
 *
 * Tabla: `orders` — relación 1:N con `order_items`, `order_status_history`, `order_custom_designs`.
 *
 * Registra la transacción completa: productos, cantidades,
 * costos de envío, impuestos, descuentos y estado del pedido.
 */
class Order extends Model
{
    //
}
