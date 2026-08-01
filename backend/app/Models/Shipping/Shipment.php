<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;

/**
 * Envío associado a un pedido.
 *
 * Tabla: `shipments` — relación N:1 con `orders` y 1:N con `shipment_trackings`.
 *
 * Registra el paquete enviado: empresa de mensajería,
 * número de guía, dirección de destino y estado del envío.
 */
class Shipment extends Model
{
    //
}
