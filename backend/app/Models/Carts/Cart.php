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

    /**
     * Filtrar carts con status `active`. NO chequea expiración — usar
     * combinado con `scopeNotExpired()` para queries de checkout real.
     *
     * @param  Builder<Cart>  $query
     * @return Builder<Cart>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Filtrar carts por FK `customer_id`.
     *
     * @param  Builder<Cart>  $query
     * @param  int  $customerId
     * @return Builder<Cart>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Filtrar carts NO expirados. Implementa el contrato:
     *   - `expires_at IS NULL`  → permanente (no expira), incluir
     *   - `expires_at > NOW()`  → vigente, incluir
     *   - `expires_at <= NOW()` → expirado, excluir
     *
     * **Logica sutil**: si una query de checkout omite este scope, el
     * cliente podria ver carritos expirados como si fueran validos.
     * SIEMPRE combinar `scopeActive()` con `scopeNotExpired()`.
     *
     * @param  Builder<Cart>  $query
     * @return Builder<Cart>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
