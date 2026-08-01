<?php

namespace App\Models\Returns;

use Illuminate\Database\Eloquent\Model;

/**
 * Artículo individual incluido en una solicitud de devolución.
 *
 * Tabla: `return_request_items` — relación N:1 con `return_requests`.
 *
 * Referencia al producto, cantidad a devolver y motivo
 * específico de la devolución de ese artículo.
 */
class ReturnRequestItem extends Model
{
    //
}
