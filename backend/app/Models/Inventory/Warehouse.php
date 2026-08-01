<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * Almacén físico o virtual donde se almacenan los productos.
 *
 * Tabla: `warehouses` — relación 1:N con `inventories`.
 *
 * Representa un punto de almacenamiento con dirección,
 * capacidad y estado de operación.
 */
class Warehouse extends Model
{
    //
}
