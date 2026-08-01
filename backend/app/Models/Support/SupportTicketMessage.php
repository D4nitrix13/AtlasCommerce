<?php

namespace App\Models\Support;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje dentro de un ticket de soporte.
 *
 * Tabla: `support_ticket_messages` — relación N:1 con `support_tickets`.
 */
class SupportTicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'message',
    ];

    protected $casts = [
        'messaged_at' => 'datetime',
    ];

    /**
     * Ticket al que pertenece el mensaje.
     */
    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    /**
     * Usuario que envió el mensaje (opcional — mensajes del sistema).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByTicket($query, int $ticketId)
    {
        return $query->where('support_ticket_id', $ticketId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('messaged_at', 'desc');
    }
}
