<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Línea individual de una factura.
 *
 * Tabla: `invoice_items` — relación N:1 con `invoices`.
 *
 * Detalla cada producto facturado con su cantidad,
 * precio unitario, impuestos y subtotal.
 */
class InvoiceItem extends Model
{
    //
}
