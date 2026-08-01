<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría del sistema.
 *
 * Tabla: `audits` — relación N:1 con `users`.
 *
 * Registra acciones significativas realizadas por los usuarios:
 * creación, edición, eliminación de registros con valores anteriores y nuevos.
 */
class Audit extends Model
{
    //
}
