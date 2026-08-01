<?php

namespace App\Models\Purchases;

use Illuminate\Database\Eloquent\Model;

/**
 * Artículo individual dentro de una orden de compra.
 *
 * Tabla: `purchase_items` — relación N:1 con `purchases`.
 *
 * Referencia al producto, cantidad solicitada,
 * costo unitario y subtotal de la línea.
 */
class PurchaseItem extends Model
{
    //
}
