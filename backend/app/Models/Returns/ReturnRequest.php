<?php

namespace App\Models\Returns;

use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\LogsActivity;
use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Solicitud de devolución de un cliente.
 *
 * Tabla: `return_requests` — relación N:1 con `stores`, `orders`, `customers`.
 * 1:N con `return_request_items` y `refunds`.
 *
 * Las devoluciones pasan por estados: pending → approved/rejected → completed.
 * Solo se procesan reembolsos para devoluciones aprobadas.
 *
 * @property int $id
 * @property int $store_id
 * @property int $order_id
 * @property int $customer_id
 * @property string $reason
 * @property string $status
 * @property Carbon $requested_at
 * @property Carbon|null $resolved_at
 */
class ReturnRequest extends Model
{
    use BelongsToStore;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'store_id',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }
}
