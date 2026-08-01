<?php

namespace App\Models\Shipping;

use App\Models\Concerns\BelongsToStore;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Envío asociado a un pedido.
 *
 * Tabla: `shipments` — relación N:1 con `orders`, `shipping_companies`,
 * `shipping_zones`, `currencies`. 1:N con `shipment_trackings`.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $shipping_company_id
 * @property int|null $shipping_zone_id
 * @property int $currency_id
 * @property string|null $tracking_code
 * @property int $shipping_cost_cents
 * @property string $status
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 */
class Shipment extends Model
{
    use BelongsToStore;
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'shipping_company_id',
        'shipping_zone_id',
        'currency_id',
        'tracking_code',
        'shipping_cost_cents',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipping_cost_cents' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopeByOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }
}
