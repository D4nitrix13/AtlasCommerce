<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Enums\OrderStatus;
use App\Models\Auth\User;
use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\LogsActivity;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Settings\Currency;
use App\Models\Shipping\Shipment;
use App\Models\Stores\SalesChannel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pedido realizado por un cliente.
 *
 * Tabla: `orders` — relación 1:N con `order_items`, `order_status_histories`,
 * `order_custom_designs`, `invoices`, `shipments`. N:1 con `customers`,
 * `customer_addresses`, `stores`, `sales_channels`, `users`, `currencies`.
 *
 * **Scopes más usados** (ver método por método):
 * - `scopePending` / `scopeProcessing` / `scopeCompleted` / `scopeCancelled`
 *   — filtran por `status` (enum `OrderStatus`).
 * - `scopeByCustomer(int $customerId)` / `scopeByStore(int $storeId)`
 *   — atajos para queries filtradas por FK.
 *
 * **Soft delete**: el modelo usa `SoftDeletes` trait. Los scopes de status
 * NO filtran soft-deleted por default — usar `->withTrashed()` si se
 * necesitan ver cancelados hace tiempo.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $shipping_address_id
 * @property int $store_id
 * @property int|null $sales_channel_id
 * @property int|null $user_creator_id
 * @property int $currency_id
 * @property string $order_number
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $tax_cents
 * @property int $shipping_cents
 * @property int $total_cents
 * @property string $exchange_rate_snapshot
 * @property int $total_in_base_currency_cents
 * @property OrderStatus $status
 * @property Carbon $ordered_at
 * @property string|null $notes
 */
class Order extends Model
{
    use BelongsToStore;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'shipping_address_id',
        'store_id',
        'sales_channel_id',
        'user_creator_id',
        'currency_id',
        'order_number',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'shipping_cents',
        'total_cents',
        'exchange_rate_snapshot',
        'total_in_base_currency_cents',
        'status',
        'ordered_at',
        'notes',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'tax_cents' => 'integer',
        'shipping_cents' => 'integer',
        'total_cents' => 'integer',
        'total_in_base_currency_cents' => 'integer',
        'exchange_rate_snapshot' => 'decimal:8',
        'ordered_at' => 'datetime',
        'status' => OrderStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function customDesigns(): HasMany
    {
        return $this->hasMany(OrderCustomDesign::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Filtrar orders con status `Pending` (pago aún no confirmado / items
     * no preparados). Equivalente a `where('status', OrderStatus::Pending->value)`.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: OrderStatus::Pending->value);
    }

    /**
     * Filtrar orders con status `Processing` (pago confirmado, en preparación
     * o despacho). Equivalente a `where('status', OrderStatus::Processing->value)`.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: OrderStatus::Processing->value);
    }

    /**
     * Filtrar orders con status `Completed` (entregado, cerrado). Equivalente
     * a `where('status', OrderStatus::Completed->value)`.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: OrderStatus::Completed->value);
    }

    /**
     * Filtrar orders con status `Cancelled` (antes o después del pago).
     * No confundir con soft-delete: una order cancelada puede seguir
     * existiendo en la DB (auditoría). Usar `->withTrashed()` solo si
     * se quieren ver soft-deleted (caso edge).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where(column: 'status', operator: '=', value: OrderStatus::Cancelled->value);
    }

    /**
     * Filtrar orders por FK `customer_id`.
     *
     * @param  Builder<Order>  $query
     * @param  int  $customerId  ID del customer (PK en tabla `customers`)
     * @return Builder<Order>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where(column: 'customer_id', operator: '=', value: $customerId);
    }

    /**
     * Filtrar orders por FK `store_id`.
     *
     * Útil en contextos multi-tenant para asegurar scoping. NO es un
     * substitute del middleware `store.domain` — usar ambos.
     *
     * @param  Builder<Order>  $query
     * @param  int  $storeId  ID del store (PK en tabla `stores`)
     * @return Builder<Order>
     */
    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }
}
