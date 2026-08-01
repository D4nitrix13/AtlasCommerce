<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa un dispositivo de confianza registrado por un usuario.
 *
 * Tabla: `trusted_devices` — relación N:1 con `users`.
 *
 * Permite al usuario recordar dispositivos para omitir
 * la verificación de dos factores en futuros inicios de sesión.
 */
class TrustedDevice extends Model
{
    //
}
