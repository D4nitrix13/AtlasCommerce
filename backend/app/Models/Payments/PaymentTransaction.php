<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

/**
 * Transacción individual enviada al proveedor de pago.
 *
 * Tabla: `payment_transactions` — relación N:1 con `payments`.
 *
 * Registra cada intento de cobro, reembolso o cargo
 * con su estado, referencia externa y metadatos de respuesta.
 */
class PaymentTransaction extends Model
{
    //
}
