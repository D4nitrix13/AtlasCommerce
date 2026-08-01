<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

/**
 * Cuota individual de un pago fraccionado.
 *
 * Tabla: `payment_installments` — relación N:1 con `payments`.
 *
 * Divide un pago en múltiples cuotas cuando se ofrece
 * financiamiento al cliente.
 */
class PaymentInstallment extends Model
{
    //
}
