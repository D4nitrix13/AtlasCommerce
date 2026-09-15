<?php

declare(strict_types=1);

namespace App\Models\Carts;

use App\Models\Catalog\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo individual dentro de una lista de deseos.
 *
 * Tabla: `wishlist_items` — relación N:1 con `wishlists`.
 *
 * @property int $id
 * @property int $wishlist_id
 * @property int $product_id
 * @property Carbon $added_at
 */
class WishlistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'wishlist_id',
        'product_id',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    /**
     * Lista de deseos a la que pertenece.
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * Producto en la lista.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
