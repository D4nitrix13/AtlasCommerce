<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de cambios de estado de un pedido.
 *
 * Tabla: `order_status_history` — relación N:1 con `orders`.
 *
 * Registra cada transición de estado (pendiente, procesando,
 * enviado, entregado, cancelado) con timestamp.
 */
class OrderStatusHistory extends Model
{
    //
}
