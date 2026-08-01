<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

/**
 * Notificación enviada a un usuario.
 *
 * Tabla: `notifications` — relación N:1 con `users`.
 *
 * Almacena notificaciones del sistema: tipo, contenido,
 * estado de lectura y datos adicionales en formato JSON.
 */
class Notification extends Model
{
    //
}
