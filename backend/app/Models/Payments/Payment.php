<?php

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

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Invoice::class,
            table: 'invoice_payments',
        )->withPivot(['applied_amount_cents', 'applied_at']);
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

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
