<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Pago registrado contra una factura.
 *
 * Tabla: `invoice_payments` — relación N:1 con `invoices`.
 *
 * Registra cada abono o pago completo realizado
 * para saldar el saldo de una factura.
 */
class InvoicePayment extends Model
{
    //
}
