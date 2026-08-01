<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Payments\InvoicePayment;
use App\Models\Payments\Payment;
use App\Models\Settings\Currency;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Factura generada para un pedido.
 *
 * Tabla: `invoices` — relación N:1 con `orders`, `customers`, `stores`,
 * `sales_channels`, `users`, `currencies`. 1:N con `invoice_items`,
 * `invoice_status_histories`, `invoice_payments`.
 *
 * Los montos se almacenan en centavos. El estado de pago
 * (`payment_status`) se sincroniza con la suma de
 * `invoice_payments.applied_amount_cents`. El cálculo de paid
 * y balance siempre debe hacerse consultando la pivot, nunca
 * leyendo columnas denormalizadas — no existen en esta versión.
 *
 * @property int $id
 * @property int|null $order_id
 * @property int $customer_id
 * @property int $store_id
 * @property int|null $sales_channel_id
 * @property int|null $user_issuer_id
 * @property int $currency_id
 * @property string $invoice_number
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property string $exchange_rate_snapshot
 * @property string $payment_status
 * @property string $invoice_status
 * @property Carbon $issued_at
 * @property Carbon|null $due_at
 * @property string|null $notes
 */
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'store_id',
        'sales_channel_id',
        'user_issuer_id',
        'currency_id',
        'invoice_number',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'exchange_rate_snapshot',
        'payment_status',
        'invoice_status',
        'issued_at',
        'due_at',
        'notes',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'tax_cents' => 'integer',
        'total_cents' => 'integer',
        'exchange_rate_snapshot' => 'decimal:8',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    /**
     * Suma de los pagos aplicados a esta factura (en centavos).
     *
     * Es la única fuente de verdad para el cálculo de "cuánto
     * lleva pagado" — no hay columnas denormalizadas.
     */
    public function paidAmountCents(): int
    {
        return (int) $this->invoicePayments()->sum('applied_amount_cents');
    }

    /**
     * Saldo pendiente en centavos derivado de total - pagos aplicados.
     */
    public function pendingBalanceCents(): int
    {
        return $this->total_cents - $this->paidAmountCents();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_issuer_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(InvoiceStatusHistory::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'store_id', 'store_id')
            ->whereHas('invoicePayments', fn ($q) => $q->where('invoice_id', $this->id));
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePartial(Builder $query): Builder
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('invoice_status', 'issued');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('invoice_status', 'cancelled');
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
