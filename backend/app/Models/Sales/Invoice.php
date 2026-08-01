<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Factura generada para un pedido.
 *
 * Tabla: `invoices` — relación N:1 con `orders` y 1:N con
 * `invoice_items`, `invoice_status_histories`.
 *
 * Documento fiscal que detalla los productos, impuestos,
 * descuentos y totales de una transacción.
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
        'invoice_number',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'pending_balance',
        'payment_status',
        'invoice_status',
        'due_at',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    /**
     * Orden asociada (opcional — facturas directas sin orden previa).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Cliente de la factura.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Tienda asociada (opcional — facturas del panel admin).
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Canal de venta de origen.
     */
    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    /**
     * Usuario administrador que emitió la factura.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_issuer_id');
    }

    /**
     * Ítems de la factura.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Historial de cambios de estado.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(InvoiceStatusHistory::class);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePartial($query)
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopeIssued($query)
    {
        return $query->where('invoice_status', 'issued');
    }

    public function scopeCancelled($query)
    {
        return $query->where('invoice_status', 'cancelled');
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
