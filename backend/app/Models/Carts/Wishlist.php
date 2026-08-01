<?php

namespace App\Models\Carts;

use Illuminate\Database\Eloquent\Model;

/**
 * Lista de deseos de un usuario.
 *
 * Tabla: `wishlists` — relación N:1 con `users` o `customers`.
 *
 * Permite guardar productos que el usuario desea adquirir
 * en el futuro para fácil acceso.
 */
class Wishlist extends Model
{
    //
}
