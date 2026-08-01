<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa una categoría de productos.
 *
 * Tabla: `categories` — relación auto-relacionada (padre/hijos) y 1:N con `products`.
 *
 * Estructura jerárquica para organizar los productos del catálogo.
 */
class Category extends Model
{
    //
}
