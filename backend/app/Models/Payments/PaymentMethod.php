<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

/**
 * Método de pago configurado en el sistema.
 *
 * Tabla: `payment_methods` — relación 1:N con `payments`.
 *
 * Define los proveedores de pago disponibles (tarjeta, PayPal, etc.)
 * y su estado de habilitación.
 */
class PaymentMethod extends Model
{
    //
}
