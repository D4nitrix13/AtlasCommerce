<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * Movimiento de entrada o salida de inventario.
 *
 * Tabla: `inventory_movements` — relación N:1 con `inventories`.
 *
 * Registra cada transacción que afecta el stock:
 * entradas, salidas, ajustes, transferencias entre almacenes.
 */
class InventoryMovement extends Model
{
    //
}
