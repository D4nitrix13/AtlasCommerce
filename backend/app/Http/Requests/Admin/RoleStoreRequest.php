<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/roles.
 *
 * Reglas:
 *  - name: string, requerido, único libre.
 *  - slug: kebab-case, requerido, único en `roles.slug`.
 *  - description: opcional, max 500 chars.
 *  - is_active: bool, default true.
 *  - permissions: array opcional de slugs existentes en el catálogo.
 */
class RoleStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe estar en kebab-case (sólo minúsculas, números y guiones).',
            'slug.unique' => 'Ya existe un rol con ese slug.',
            'permissions.*.exists' => 'Uno o más permisos no existen en el catálogo.',
        ];
    }
}
