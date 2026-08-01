<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Mensaje dentro de un ticket de soporte.
 *
 * Tabla: `support_ticket_messages` — relación N:1 con `support_tickets`.
 *
 * Registra la conversación entre el cliente y el equipo de soporte,
 * con remitente, contenido y timestamp.
 */
class SupportTicketMessage extends Model
{
    //
}
