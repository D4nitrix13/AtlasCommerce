<?php

declare(strict_types=1);

namespace App\Models\Payments;

use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\LogsActivity;
use App\Models\Customers\Customer;
use App\Models\Sales\Invoice;
use App\Models\Settings\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * Registro de un pago realizado por un cliente.
 *
 * Tabla: `payments` — relación N:1 con `customers`, `payment_methods`,
 * `stores`, `currencies`. 1:N con `payment_transactions`,
 * `invoice_payments`. N:N con `invoices` vía `invoice_payments`.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $payment_method_id
 * @property int $store_id
 * @property int $currency_id
 * @property int $amount_cents
 * @property string $exchange_rate_snapshot
 * @property string|null $reference
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string|null $notes
 */
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToStore;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'payment_method_id',
        'store_id',
        'currency_id',
        'amount_cents',
        'exchange_rate_snapshot',
        'reference',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'exchange_rate_snapshot' => 'decimal:8',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * N:M con Invoice via tabla pivot `invoice_payments`. Permite que un
     * payment se aplique a múltiples invoices (ej: payment grande que
     * cubre 3 invoices).
     *
     * Pivot expone:
     * - `applied_amount_cents` (int) — cuánto se aplicó a ESTA invoice
     *   (un mismo payment puede tener 3 rows con distintos amounts).
     * - `applied_at` (datetime) — cuándo se aplicó (auditoría).
     *
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Invoice::class,
            table: 'invoice_payments',
        )->withPivot(['applied_amount_cents', 'applied_at']);
    }

    /**
     * Filtrar payments con status `pending` (string literal, NO enum).
     * Status como string es fragil a typos — preferir `Payment::firstWhere('status', 'pending')`
     * o usar constantes de clase si existen.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filtrar payments con status `completed` (transaction ejecutada
     * exitosamente en el gateway; fondos confirmados).
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Filtrar payments con status `failed` (gateway rechazó la transacción
     * o fue cancelada antes de completarse). NO confundir con `cancelled`
     * (que es decisión del usuario) o `refunded` (que requiere refund).
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Filtrar payments por FK `customer_id`.
     *
     * @param  Builder<Payment>  $query
     * @param  int  $customerId  ID del customer
     * @return Builder<Payment>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
