<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un usuario autenticado.
 *
 * Shape:
 *  - id, email, full_name (computed)
 *  - role_id (nullable — el user puede no tener rol)
 *  - is_active
 *  - permissions[] — slugs efectivos del usuario (vacío si es admin
 *    porque el bypass no expone los slugs al frontend; el frontend
 *    asume acceso total cuando role.slug === 'admin')
 *
 * NO expone password ni tokens. NO expone 2FA secrets ni códigos.
 *
 * @property int $id
 * @property string $email
 * @property string $first_name
 * @property string $last_name
 * @property int|null $role_id
 * @property bool $is_active
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissions = [];

        if ($this->role !== null && $this->role->slug !== 'admin') {
            // `roles.is_active` ya se valida vía User::hasPermission en
            // el backend. La tabla `permissions` no tiene `is_active` —
            // un permission existe o no existe, no se desactiva.
            $permissions = $this->role->permissions()
                ->pluck(column: 'slug')
                ->all();
        }

        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'slug' => $this->role->slug,
                'name' => $this->role->name,
            ] : null,
            'role_id' => $this->role_id,
            'is_active' => (bool) $this->is_active,
            'permissions' => $permissions,
        ];
    }
}