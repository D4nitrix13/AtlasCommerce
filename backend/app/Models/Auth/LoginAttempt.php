<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Registra intentos de inicio de sesión del usuario.
 *
 * Tabla: `login_attempts` — relación N:1 con `users`.
 *
 * Almacena cada intento de acceso con su resultado (exitoso/fallido),
 * dirección IP, user agent y timestamp. Utilizable para detectar
 * ataques de fuerza bruta y bloquear cuentas temporalmente.
 */
class LoginAttempt extends Model
{
    //
}
