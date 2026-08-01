<?php

namespace App\Models\Payments;

use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuota individual de un pago fraccionado.
 *
 * Tabla: `payment_installments` — relación N:1 con `invoices`.
 *
 * Registra cada cuota programada de una factura a plazos,
 * con monto, fecha de vencimiento y estado.
 */
class PaymentInstallment extends Model
{
    protected $fillable = [
        'invoice_id',
        'installment_number',
        'scheduled_amount',
        'paid_amount',
        'due_date',
        'status',
    ];

    protected $casts = [
        'scheduled_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Factura asociada a la cuota.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now());
    }

    public function scopeByInvoice($query, int $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }
}
