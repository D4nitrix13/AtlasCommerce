<?php

declare(strict_types=1);

namespace App\Models\Payments;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transacción individual enviada al proveedor de pago.
 *
 * Tabla: `payment_transactions` — relación N:1 con `payments`.
 *
 * @property int $id
 * @property int $payment_id
 * @property string $payment_provider
 * @property string|null $transaction_code
 * @property array|null $provider_response
 * @property int|null $amount_cents
 * @property string $status
 * @property Carbon $transacted_at
 */
class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'payment_provider',
        'transaction_code',
        'provider_response',
        'amount_cents',
        'status',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'amount_cents' => 'integer',
        'transacted_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('payment_provider', $provider);
    }
}
