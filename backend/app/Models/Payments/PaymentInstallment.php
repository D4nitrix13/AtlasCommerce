<?php

namespace App\Models\Payments;

use App\Models\Sales\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuota individual de un pago fraccionado.
 *
 * Tabla: `payment_installments` — relación N:1 con `invoices`.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $installment_number
 * @property int $scheduled_amount_cents
 * @property int $paid_amount_cents
 * @property Carbon $due_date
 * @property string $status
 * @property Carbon|null $paid_at
 */
class PaymentInstallment extends Model
{
    protected $fillable = [
        'invoice_id',
        'installment_number',
        'scheduled_amount_cents',
        'paid_amount_cents',
        'due_date',
        'status',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'scheduled_amount_cents' => 'integer',
        'paid_amount_cents' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeByInvoice(Builder $query, int $invoiceId): Builder
    {
        return $query->where('invoice_id', $invoiceId);
    }
}
