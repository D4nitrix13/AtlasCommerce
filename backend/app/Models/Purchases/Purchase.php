<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;

/**
 * Orden de compra a un proveedor.
 *
 * Tabla: `purchases` — relación N:1 con `suppliers` y 1:N con `purchase_items`.
 *
 * Registra la adquisición de mercancía: productos solicitados,
 * cantidades, costos unitarios y estado de la orden.
 */
class Purchase extends Model
{
    //
}
