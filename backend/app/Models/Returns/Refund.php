<?php

namespace App\Models\Returns;

use App\Models\Payments\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reembolso procesado para una devolución.
 *
 * Tabla: `refunds` — relación N:1 con `return_requests` y `payment_methods`.
 */
class Refund extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'return_request_id',
        'payment_method_id',
        'amount',
        'status',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    /**
     * Solicitud de devolución asociada.
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /**
     * Método de pago utilizado para el reembolso.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
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

    public function scopeByReturnRequest($query, int $returnRequestId)
    {
        return $query->where('return_request_id', $returnRequestId);
    }
}
