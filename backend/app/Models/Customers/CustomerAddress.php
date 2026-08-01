<?php

namespace App\Models\Customers;

use Illuminate\Database\Eloquent\Model;

/**
 * Dirección de envío o facturación de un cliente.
 *
 * Tabla: `customer_addresses` — relación N:1 con `customers`.
 *
 * Almacena la información postal: dirección, ciudad, estado,
 * código postal, país y coordenadas geográficas.
 */
class CustomerAddress extends Model
{
    //
}
