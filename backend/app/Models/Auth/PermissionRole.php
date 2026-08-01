<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla pivote entre permisos y roles.
 *
 * Tabla: `permission_roles` — relación N:1 con `permissions` y `roles`.
 *
 * Modela la relación many-to-many entre permisos y roles,
 * determinando qué permisos tiene asignados cada rol.
 */
class PermissionRole extends Model
{
    //
}
