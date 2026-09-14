<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/roles/{role}/permissions.
 *
 * Body esperado:
 *  - permissions: array de slugs de permisos existentes. Vacío = limpia.
 *
 * Reemplaza atómicamente el set de permisos del rol.
 */
class RolePermissionsSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.required' => 'Tenés que enviar el array de permisos.',
            'permissions.*.exists' => 'Uno o más permisos no existen en el catálogo.',
        ];
    }
}
