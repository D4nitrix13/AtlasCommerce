<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;

/**
 * Evento de seguimiento de un envío.
 *
 * Tabla: `shipment_trackings` — relación N:1 con `shipments`.
 *
 * Registra cada actualización de la cadena de custodia:
 * recogido, en tránsito, en reparto, entregado.
 */
class ShipmentTracking extends Model
{
    //
}
