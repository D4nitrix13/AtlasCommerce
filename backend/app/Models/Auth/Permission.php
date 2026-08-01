<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Representa un permiso individual del sistema.
 *
 * Tabla: `permissions` — relación N:N con `roles` a través de `permission_role`.
 *
 * Cada permiso define una acción específica que un rol puede realizar.
 * Se agrupan por módulo para facilitar la administración.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $module
 * @property string|null $description
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

    /**
     * Filtra permisos por módulo específico.
     *
     * @param  Builder<Permission>  $query  Consulta del modelo.
     * @param  string  $module  Nombre del módulo.
     * @return Builder<Permission>
     */
    public function scopeByModule(Builder $query, string $module): Builder
    {
        return $query->where(column: 'module', value: $module);
    }

    /**
     * Filtra permisos por slug específico.
     *
     * @param  Builder<Permission>  $query  Consulta del modelo.
     * @param  string  $slug  Slug del permiso.
     * @return Builder<Permission>
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where(column: 'slug', value: $slug);
    }
}
