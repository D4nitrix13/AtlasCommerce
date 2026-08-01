<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;

/**
 * Empresa de mensajería o paquetería.
 *
 * Tabla: `shipping_companies` — relación 1:N con `shipments`.
 *
 * Define las empresas de envío disponibles:
 * nombre, API de rastreo, tarifas y zonas de cobertura.
 */
class ShippingCompany extends Model
{
    //
}
