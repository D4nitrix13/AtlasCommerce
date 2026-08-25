<?php

declare(strict_types=1);

namespace App\Models\Carts;

use App\Models\Concerns\BelongsToStore;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * Representa el carrito de compras de un cliente.
 *
 * Tabla: `carts` — relación N:1 con `customers`, `stores`, `currencies`.
 * 1:N con `cart_items`.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $store_id
 * @property int $currency_id
 * @property string $status
 * @property Carbon|null $expires_at
 */
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    use BelongsToStore;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'store_id',
        'currency_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
