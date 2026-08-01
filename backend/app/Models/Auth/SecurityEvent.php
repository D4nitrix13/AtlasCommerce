<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Registra eventos de seguridad del sistema.
 *
 * Tabla: `security_events` — relación N:1 con `users`.
 *
 * Almacena eventos relevantes para la auditoría de seguridad,
 * como inicios de sesión sospechosos, cambios de contraseña,
 * y activación/desactivación de autenticación de dos factores.
 */
class SecurityEvent extends Model
{
    //
}
