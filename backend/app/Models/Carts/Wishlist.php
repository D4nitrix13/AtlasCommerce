<?php

declare(strict_types=1);

namespace App\Models\Carts;

use App\Models\Concerns\BelongsToStore;
use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lista de deseos de un usuario.
 *
 * Tabla: `wishlists` — relación N:1 con `customers` y 1:N con `wishlist_items`.
 *
 * Un cliente puede tener múltiples listas de deseos, pero solo una
 * puede ser la predeterminada.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $store_id
 * @property string $name
 * @property bool $is_default
 */
class Wishlist extends Model
{
    use BelongsToStore;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'store_id',
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Cliente propietario de la lista.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Ítems de la lista de deseos.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * Filtra listas de deseos por cliente específico.
     *
     * @param  Builder<Wishlist>  $query  Consulta del modelo.
     * @param  int  $customerId  Identificador del cliente.
     * @return Builder<Wishlist>
     */
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where(column: 'customer_id', value: $customerId);
    }

    /**
     * Filtra la lista de deseos predeterminada.
     *
     * @param  Builder<Wishlist>  $query  Consulta del modelo.
     * @return Builder<Wishlist>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where(column: 'is_default', value: true);
    }
}
