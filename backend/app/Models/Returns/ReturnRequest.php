<?php

namespace App\Models\Returns;

use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Solicitud de devolución de un cliente.
 *
 * Tabla: `return_requests` — relación 1:N con `return_request_items` y `refunds`.
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }
}
