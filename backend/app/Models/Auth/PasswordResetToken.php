<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Almacena tokens de recuperación de contraseña.
 *
 * Tabla: `password_reset_tokens` — relación N:1 con `users`.
 *
 * Cada token se genera cuando el usuario solicita restablecer su contraseña.
 * El token tiene un tiempo de expiración y se invalida después de su uso.
 */
class PasswordResetToken extends Model
{
    //
}
