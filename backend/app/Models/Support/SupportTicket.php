<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Ticket de soporte creado por un cliente.
 *
 * Tabla: `support_tickets` — relación 1:N con `support_ticket_messages`.
 *
 * Registra las solicitudes de ayuda: categoría, asunto,
 * estado de atención y prioridad.
 */
class SupportTicket extends Model
{
    //
}
