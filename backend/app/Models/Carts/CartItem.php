<?php

namespace App\Models\Carts;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa un artículo dentro del carrito de compras.
 *
 * Tabla: `cart_items` — relación N:1 con `carts`.
 *
 * Almacena la referencia al producto, variante, cantidad
 * y precio unitario al momento de agregarlo al carrito.
 */
class CartItem extends Model
{
    //
}
