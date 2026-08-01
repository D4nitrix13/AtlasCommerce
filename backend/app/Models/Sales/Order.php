<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Stores\SalesChannel;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pedido realizado por un cliente.
 *
 * Tabla: `orders` — relación 1:N con `order_items`, `order_status_histories`,
 * `order_custom_designs`.
 *
 * Registra la transacción completa: productos, cantidades,
 * costos de envío, impuestos, descuentos y estado del pedido.
 */
class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'shipping_address_id',
        'store_id',
        'sales_channel_id',
        'user_creator_id',
        'order_number',
        'subtotal',
        'discount',
        'tax',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'ordered_at' => 'datetime',
    ];

    /**
     * Cliente que realizó la orden.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Dirección de envío (opcional — retiro en tienda).
     */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    /**
     * Tienda asociada a la orden (opcional — órdenes del panel admin).
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Canal de venta de origen (web, POS, teléfono, etc.).
     */
    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    /**
     * Usuario administrador que creó la orden manualmente.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_creator_id');
    }

    /**
     * Ítems de la orden.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Historial de cambios de estado.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Diseños personalizados asociados.
     */
    public function customDesigns(): HasMany
    {
        return $this->hasMany(OrderCustomDesign::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
