<?php

namespace App\Models\Payments;

use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago registrado contra una factura.
 *
 * Tabla: `invoice_payments` — relación N:1 con `invoices` y `payments`.
 *
 * Registra cada abono o pago completo realizado
 * para saldar el saldo de una factura.
 */
class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_id',
        'applied_amount',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    /**
     * Factura a la que se aplica el pago.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Pago registrado.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
