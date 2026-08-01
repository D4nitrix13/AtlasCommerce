<?php

namespace App\Models\Auth;

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
