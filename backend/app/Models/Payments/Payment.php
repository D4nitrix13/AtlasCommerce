<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de un pago realizado por un cliente.
 *
 * Tabla: `payments` — relación N:1 con `orders`.
 *
 * Almacena el monto, método de pago, estado y referencia
 * de cada transacción de pago asociada a un pedido.
 */
class Payment extends Model
{
    //
}
