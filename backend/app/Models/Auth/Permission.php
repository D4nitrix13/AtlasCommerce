<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa un permiso individual del sistema.
 *
 * Tabla: `permissions` — relación N:N con `roles` a través de `permission_roles`.
 *
 * Cada permiso define una acción específica que un rol puede realizar.
 * Se utiliza en conjunto con el sistema de roles para el control de acceso.
 */
class Permission extends Model
{
    //
}
