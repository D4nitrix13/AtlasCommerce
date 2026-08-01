<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Reseña y calificación de un producto por un cliente.
 *
 * Tabla: `product_reviews` — relación N:1 con `products` y `customers`.
 *
 * Almacena el comentario, calificación (1-5 estrellas),
 * fecha de compra y estado de publicación.
 */
class ProductReview extends Model
{
    //
}
