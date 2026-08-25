<?php

declare(strict_types=1);

namespace App\Models\Shipping;

use App\Enums\ShipmentStatus;
use App\Models\Concerns\LogsActivity;
use App\Models\Sales\Order;
use App\Models\Settings\Currency;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
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
    use HasFactory;
    use LogsActivity;
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
        'status' => ShipmentStatus::class,
        'shipping_cost_cents' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Tienda del envío, derivada a través de la orden.
     *
     * No usamos `BelongsToStore` directo porque `shipments` no tiene
     * `store_id` (se deriva vía `order.store_id`). El aislamiento
     * multi-tenant se obtiene filtrando por la orden primero.
     */
    public function store(): HasOneThrough
    {
        return $this->hasOneThrough(
            related: Store::class,
            through: Order::class,
            firstKey: 'id',
            secondKey: 'id',
            localKey: 'order_id',
            secondLocalKey: 'store_id',
        );
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: ShipmentStatus::Pending->value);
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: ShipmentStatus::Shipped->value);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: ShipmentStatus::InTransit->value);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: ShipmentStatus::Delivered->value);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: ShipmentStatus::Cancelled->value);
    }

    public function scopeByOrder(Builder $query, int $orderId): Builder
    {
        return $query->where(column: 'order_id', operator: '=', value: $orderId);
    }
}
