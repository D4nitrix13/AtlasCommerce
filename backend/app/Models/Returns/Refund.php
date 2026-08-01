<?php

declare(strict_types=1);

namespace App\Models\Returns;

use App\Enums\RefundStatus;
use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\LogsActivity;
use App\Models\Payments\PaymentMethod;
use App\Models\Settings\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reembolso procesado para una devolución.
 *
 * Tabla: `refunds` — relación N:1 con `return_requests`, `payment_methods`,
 * `currencies`.
 *
 * @property int $id
 * @property int $return_request_id
 * @property int|null $payment_method_id
 * @property int $currency_id
 * @property int $amount_cents
 * @property string $exchange_rate_snapshot
 * @property RefundStatus $status
 * @property Carbon|null $refunded_at
 */
class Refund extends Model
{
    use BelongsToStore;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'return_request_id',
        'payment_method_id',
        'currency_id',
        'amount_cents',
        'exchange_rate_snapshot',
        'status',
        'refunded_at',
    ];

    protected $casts = [
        'status' => RefundStatus::class,
        'amount_cents' => 'integer',
        'exchange_rate_snapshot' => 'decimal:8',
        'refunded_at' => 'datetime',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: RefundStatus::Pending->value);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: RefundStatus::Completed->value);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: RefundStatus::Failed->value);
    }

    public function scopeByReturnRequest(Builder $query, int $returnRequestId): Builder
    {
        return $query->where(column: 'return_request_id', operator: '=', value: $returnRequestId);
    }
}
