<?php

namespace App\Models\Returns;

use Illuminate\Database\Eloquent\Model;

/**
 * Reembolso procesado para una devolución.
 *
 * Tabla: `refunds` — relación N:1 con `return_requests`.
 *
 * Registra el monto reembolsado, método de reembolso,
 * estado y referencia de la transacción.
 */
class Refund extends Model
{
    //
}
