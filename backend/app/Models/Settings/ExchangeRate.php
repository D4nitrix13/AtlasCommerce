<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cambio entre dos monedas.
 *
 * Tabla: `exchange_rates` — relación N:1 con `currencies`.
 *
 * Almacena la tasa de conversión entre monedas origen y destino,
 * con fecha de vigencia y origen de la cotización.
 */
class ExchangeRate extends Model
{
    //
}
