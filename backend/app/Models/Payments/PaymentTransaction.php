<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transacción individual enviada al proveedor de pago.
 *
 * Tabla: `payment_transactions` — relación N:1 con `payments`.
 *
 * Registra cada llamada a la pasarela de pago con su
 * respuesta, estado y código de transacción.
 */
class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'payment_provider',
        'transaction_code',
        'provider_response',
        'status',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'transacted_at' => 'datetime',
    ];

    /**
     * Pago al que pertenece la transacción.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('payment_provider', $provider);
    }
}
