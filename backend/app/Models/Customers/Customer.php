<?php

namespace App\Models\Customers;

use Illuminate\Database\Eloquent\Model;

/**
 * Cliente registrado en la tienda.
 *
 * Tabla: `customers` — relación 1:N con `customer_addresses`.
 *
 * Almacena los datos del cliente: nombre, correo electrónico,
 * teléfono y otros datos de contacto.
 */
class Customer extends Model
{
    //
}
