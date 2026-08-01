<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de cambios de estado de una factura.
 *
 * Tabla: `invoice_status_history` — relación N:1 con `invoices`.
 *
 * Registra cada transición de estado (emitida, pagada, anulada)
 * con timestamp y usuario responsable.
 */
class InvoiceStatusHistory extends Model
{
    //
}
