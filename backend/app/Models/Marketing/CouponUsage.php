<?php

declare(strict_types=1);

namespace App\Models\Marketing;

use App\Models\Customers\Customer;
use App\Models\Sales\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de uso de un cupón por un cliente.
 *
 * Tabla: `coupon_usages` — relación N:1 con `coupons`, `customers` y `orders`.
 *
 * Cada registro representa una aplicación exitosa de un cupón
 * en el contexto de una orden específica.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $customer_id
 * @property int|null $order_id
 * @property Carbon $used_at
 */
class CouponUsage extends Model
{
    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * Cupón utilizado.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Cliente que usó el cupón.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Orden en la que se aplicó.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
