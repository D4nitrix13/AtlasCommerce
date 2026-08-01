<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla pivote entre permisos y roles.
 *
 * Tabla: `permission_role` — relación N:1 con `permissions` y `roles`.
 *
 * Modela la relación many-to-many entre permisos y roles,
 * determinando qué permisos tiene asignados cada rol.
 *
 * @property int $id
 * @property int $role_id
 * @property int $permission_id
 */
class PermissionRole extends Model
{
    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    /**
     * Rol asociado.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Permiso asociado.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
