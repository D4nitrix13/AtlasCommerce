<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * Nivel de inventario de un producto o variante.
 *
 * Tabla: `inventories` — relación N:1 con `products`, `product_variants` y `warehouses`.
 *
 * Registra la cantidad disponible, reservada y en tránsito
 * para cada producto en cada almacén.
 */
class Inventory extends Model
{
    //
}
