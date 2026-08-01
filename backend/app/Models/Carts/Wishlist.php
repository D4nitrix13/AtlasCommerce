<?php

namespace App\Models\Carts;

use App\Models\Customers\Customer;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lista de deseos de un usuario.
 *
 * Tabla: `wishlists` — relación N:1 con `customers` y 1:N con `wishlist_items`.
 */
class Wishlist extends Model
{
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

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
