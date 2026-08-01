<?php

namespace App\Models\Carts;

use Illuminate\Database\Eloquent\Model;

/**
 * Artículo individual dentro de una lista de deseos.
 *
 * Tabla: `wishlist_items` — relación N:1 con `wishlists`.
 *
 * Referencia al producto guardado por el usuario en su lista de deseos.
 */
class WishlistItem extends Model
{
    //
}
