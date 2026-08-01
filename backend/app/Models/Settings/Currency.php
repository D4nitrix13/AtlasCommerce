<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * Moneda soportada por el sistema.
 *
 * Tabla: `currencies` — relación 1:N con `exchange_rates`.
 *
 * Define las monedas disponibles: código ISO, símbolo,
 * decimales y estado de habilitación.
 */
class Currency extends Model
{
    //
}
