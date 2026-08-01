<?php

namespace App\Models\Payments;

use App\Models\Sales\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago registrado contra una factura (pivot `invoice_payments`).
 *
 * Tabla: `invoice_payments` — relación N:1 con `invoices` y `payments`.
 *
 * El monto aplicado se almacena en centavos. La suma de
 * `applied_amount_cents` para una factura dada es la única
 * fuente de verdad para el saldo pagado de esa factura
 * (ver {@see Invoice::paidAmountCents()}).
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $payment_id
 * @property int $applied_amount_cents
 * @property Carbon $applied_at
 */
class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_id',
        'applied_amount_cents',
    ];

    protected $casts = [
        'applied_amount_cents' => 'integer',
        'applied_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
