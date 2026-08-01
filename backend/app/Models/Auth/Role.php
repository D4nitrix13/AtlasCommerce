<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Define un rol de usuario en el sistema.
 *
 * Tabla: `roles` — relación N:N con `permissions` a través de `permission_roles`.
 *
 * Los roles agrupan permisos para facilitar la gestión de acceso.
 * Ejemplos: admin, vendedor, cliente.
 */
class Role extends Model
{
    //
}
