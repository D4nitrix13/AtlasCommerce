<?php

namespace App\Models\Payments;

use App\Models\Customers\Customer;
use App\Models\Sales\Invoice;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro de un pago realizado por un cliente.
 *
 * Tabla: `payments` — relación N:1 con `customers`, `payment_methods`,
 * `stores`, y 1:N con `payment_transactions`, `invoice_payments`.
 */
class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'payment_method_id',
        'store_id',
        'amount',
        'reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Cliente que realizó el pago.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Método de pago utilizado.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Tienda asociada al pago.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Transacciones enviadas al proveedor de pago.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * Facturas asociadas a este pago.
     */
    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Facturas aplicadas a este pago.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Invoice::class,
            table: 'invoice_payments',
        )->withPivot('applied_amount', 'applied_at');
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

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
