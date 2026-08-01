<?php

namespace App\Models\Returns;

use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Solicitud de devolución de un cliente.
 *
 * Tabla: `return_requests` — relación 1:N con `return_request_items` y `refunds`.
 *
 * Las devoluciones pasan por estados: pending → approved/rejected → completed.
 * Solo se procesan reembolsos para devoluciones aprobadas.
 *
 * @property int $id
 * @property int $order_id
 * @property int $customer_id
 * @property string $reason
 * @property string $status
 * @property Carbon $requested_at
 * @property Carbon|null $resolved_at
 */
class ReturnRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'reason',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Orden original.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Cliente que solicitó la devolución.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Ítems incluidos en la devolución.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    /**
     * Reembolsos procesados.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Filtra devoluciones pendientes de revisión.
     *
     * @param  Builder<ReturnRequest>  $query  Consulta del modelo.
     * @return Builder<ReturnRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(column: 'status', value: 'pending');
    }

    /**
     * Filtra devoluciones aprobadas.
     *
     * @param  Builder<ReturnRequest>  $query  Consulta del modelo.
     * @return Builder<ReturnRequest>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where(column: 'status', value: 'approved');
    }

    /**
     * Filtra devoluciones rechazadas.
     *
     * @param  Builder<ReturnRequest>  $query  Consulta del modelo.
     * @return Builder<ReturnRequest>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where(column: 'status', value: 'rejected');
    }

    /**
     * Filtra devoluciones completadas.
     *
     * @param  Builder<ReturnRequest>  $query  Consulta del modelo.
     * @return Builder<ReturnRequest>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where(column: 'status', value: 'completed');
    }

    /**
     * Filtra devoluciones por cliente específico.
     *
     * @param  Builder<ReturnRequest>  $query  Consulta del modelo.
     * @param  int  $customerId  Identificador del cliente.
     * @return Builder<ReturnRequest>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where(column: 'customer_id', value: $customerId);
    }

    /**
     * Filtra devoluciones por orden específica.
     *
     * @param  Builder<ReturnRequest>  $query  Consulta del modelo.
     * @param  int  $orderId  Identificador de la orden.
     * @return Builder<ReturnRequest>
     */
    public function scopeByOrder(Builder $query, int $orderId): Builder
    {
        return $query->where(column: 'order_id', value: $orderId);
    }
}
