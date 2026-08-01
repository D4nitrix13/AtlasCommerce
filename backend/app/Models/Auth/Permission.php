<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Representa un permiso individual del sistema.
 *
 * Tabla: `permissions` — relación N:N con `roles` a través de `permission_role`.
 *
 * Cada permiso define una acción específica que un rol puede realizar.
 * Se utiliza en conjunto con el sistema de roles para el control de acceso.
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * Roles que tienen asignado este permiso.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Role::class,
            table: 'permission_role',
        );
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
