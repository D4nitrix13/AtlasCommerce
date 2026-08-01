<?php

namespace App\Models\Returns;

use Illuminate\Database\Eloquent\Model;

/**
 * Solicitud de devolución de un cliente.
 *
 * Tabla: `return_requests` — relación 1:N con `return_request_items` y `refunds`.
 *
 * Registra la solicitud de devolución con su motivo,
 * estado de revisión y resultado final.
 */
class ReturnRequest extends Model
{
    //
}
