<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para represent un rol del sistema de autenticación.
 *
 * Renderiza el rol + los slugs de sus permisos asignados (NO la
 * lista completa de Permission para evitar payloads pesados en el
 * listado). Para el detalle con la lista completa, usar
 * `RoleDetailResource` (futuro).
 *
 * @mixin \App\Models\Auth\Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'is_active' => (bool) $this->resource->is_active,
            'is_protected' => $this->resource->slug === 'admin',
            'permissions' => $this->whenLoaded(
                relationship: 'permissions',
                value: fn () => $this->resource->permissions->pluck('slug')->all(),
                default: [],
            ),
            'permissions_count' => (int) $this->resource->permissions()->count(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'deleted_at' => $this->resource->deleted_at?->toIso8601String(),
        ];
    }
}
