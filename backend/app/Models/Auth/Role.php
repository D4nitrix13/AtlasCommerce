<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Define un rol de usuario en el sistema.
 *
 * Tabla: `roles` — relación N:N con `permissions` a través de `permission_role`.
 *
 * Los roles agrupan permisos para facilitar la gestión de acceso.
 * Ejemplos: admin, vendedor, cliente.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 */
class Role extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Permisos asignados a este rol.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Permission::class,
            table: 'permission_role',
        );
    }

    /**
     * Filtra roles activos.
     *
     * @param  Builder<Role>  $query  Consulta del modelo.
     * @return Builder<Role>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true);
    }

    /**
     * Filtra roles por slug específico.
     *
     * @param  Builder<Role>  $query  Consulta del modelo.
     * @param  string  $slug  Slug del rol.
     * @return Builder<Role>
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where(column: 'slug', value: $slug);
    }
}
