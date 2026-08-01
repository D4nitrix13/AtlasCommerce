<?php

namespace App\Models\Carts;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa el carrito de compras de un usuario.
 *
 * Tabla: `carts` — relación N:1 con `users` o `customers`.
 *
 * Almacena los productos que el usuario ha seleccionado para comprar
 * pero aún no ha confirmado en un pedido.
 */
class Cart extends Model
{
    //
}
