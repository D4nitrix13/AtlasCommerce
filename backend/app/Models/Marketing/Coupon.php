<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

/**
 * Cupón de descuento aplicable a pedidos.
 *
 * Tabla: `coupons` — relación 1:N con `coupon_usages`.
 *
 * Define descuentos por porcentaje o monto fijo,
 * con validez temporal, límite de uso y condiciones de aplicación.
 */
class Coupon extends Model
{
    //
}
