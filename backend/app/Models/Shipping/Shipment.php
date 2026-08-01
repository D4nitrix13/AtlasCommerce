<?php

namespace App\Models\Shipping;

use App\Models\Sales\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Envío asociado a un pedido.
 *
 * Tabla: `shipments` — relación N:1 con `orders`, `shipping_companies`,
 * `shipping_zones`, y 1:N con `shipment_trackings`.
 */
class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'shipping_company_id',
        'shipping_zone_id',
        'tracking_code',
        'shipping_cost',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Orden asociada al envío.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Empresa de mensajería.
     */
    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    /**
     * Zona geográfica del envío.
     */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    /**
     * Eventos de seguimiento del envío.
     */
    public function trackings(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeByOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }
}
