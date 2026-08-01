<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de uso de un cupón por un cliente.
 *
 * Tabla: `coupon_usages` — relación N:1 con `coupons`.
 *
 * Rastrea cada vez que un cupón es aplicado,
 * evitando usos múltiples cuando no está permitido.
 */
class CouponUsage extends Model
{
    //
}
