<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;

/**
 * Zona geográfica para cálculo de costos de envío.
 *
 * Tabla: `shipping_zones` — relación 1:N con `shipping_methods`.
 *
 * Define regiones con sus tarifas de envío,
 * tiempos de entrega estimados y condiciones.
 */
class ShippingZone extends Model
{
    //
}
