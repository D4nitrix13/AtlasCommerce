<?php

namespace App\Models\Support;

use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ticket de soporte creado por un cliente.
 *
 * Tabla: `support_tickets` — relación 1:N con `support_ticket_messages`.
 *
 * Los tickets tienen estados (open, in_progress, closed) y prioridades
 * (low, normal, high, urgent) para gestionar el flujo de atención.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $store_id
 * @property string $subject
 * @property string $description
 * @property string $status
 * @property string $priority
 */
class SupportTicket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'store_id',
        'subject',
        'description',
        'status',
        'priority',
    ];

    /**
     * Cliente que creó el ticket.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Mensajes del ticket.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    /**
     * Filtra tickets abiertos.
     *
     * @param  Builder<SupportTicket>  $query  Consulta del modelo.
     * @return Builder<SupportTicket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(column: 'status', value: 'open');
    }

    /**
     * Filtra tickets cerrados.
     *
     * @param  Builder<SupportTicket>  $query  Consulta del modelo.
     * @return Builder<SupportTicket>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where(column: 'status', value: 'closed');
    }

    /**
     * Filtra tickets por estado específico.
     *
     * @param  Builder<SupportTicket>  $query  Consulta del modelo.
     * @param  string  $status  Estado del ticket (open, in_progress, closed).
     * @return Builder<SupportTicket>
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where(column: 'status', value: $status);
    }

    /**
     * Filtra tickets por prioridad específica.
     *
     * @param  Builder<SupportTicket>  $query  Consulta del modelo.
     * @param  string  $priority  Prioridad del ticket (low, normal, high, urgent).
     * @return Builder<SupportTicket>
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where(column: 'priority', value: $priority);
    }

    /**
     * Filtra tickets por cliente específico.
     *
     * @param  Builder<SupportTicket>  $query  Consulta del modelo.
     * @param  int  $customerId  Identificador del cliente.
     * @return Builder<SupportTicket>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where(column: 'customer_id', value: $customerId);
    }
}
