<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Factura generada para un pedido.
 *
 * Tabla: `invoices` — relación N:1 con `orders` y 1:N con `invoice_items`, `invoice_payments`, `invoice_status_history`.
 *
 * Documento fiscal que detalla los productos, impuestos,
 * descuentos y totales de una transacción.
 */
class Invoice extends Model
{
    //
}
