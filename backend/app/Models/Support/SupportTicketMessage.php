<?php

namespace App\Models\Support;

use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje dentro de un ticket de soporte.
 *
 * Tabla: `support_ticket_messages` — relación N:1 con `support_tickets`.
 *
 * Los mensajes pueden provenir del cliente o del equipo de soporte.
 * El campo `user_id` es nulo para mensajes automáticos del sistema.
 *
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $user_id
 * @property string $message
 * @property Carbon $messaged_at
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

    /**
     * Filtra mensajes por ticket específico.
     *
     * @param  Builder<SupportTicketMessage>  $query  Consulta del modelo.
     * @param  int  $ticketId  Identificador del ticket.
     * @return Builder<SupportTicketMessage>
     */
    public function scopeByTicket(Builder $query, int $ticketId): Builder
    {
        return $query->where(column: 'support_ticket_id', value: $ticketId);
    }

    /**
     * Ordena mensajes del más reciente al más antiguo.
     *
     * @param  Builder<SupportTicketMessage>  $query  Consulta del modelo.
     * @return Builder<SupportTicketMessage>
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy(column: 'messaged_at', direction: 'desc');
    }
}
