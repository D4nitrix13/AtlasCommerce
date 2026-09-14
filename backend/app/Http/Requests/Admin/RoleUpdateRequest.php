<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/roles/{role}.
 *
 * Reglas:
 *  - name: opcional, string.
 *  - slug: opcional, kebab-case, único EXCEPTO en el row actual.
 *  - El slug `admin` está protegido: no se puede renombrar.
 *  - description: opcional.
 *  - is_active: bool opcional.
 *
 * Los permisos NO se editan acá. Usar PUT /admin/roles/{role}/permissions
 * para el sync atómico (más explícito que un upsert implícito en update).
 */
class RoleUpdateRequest extends FormRequest
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
        $role = $this->route('role');
        $roleId = $role instanceof \App\Models\Auth\Role ? (int) $role->id : (int) $role;

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'slug' => [
                'sometimes',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(table: 'roles', column: 'slug')->ignore($roleId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validación cross-field: si el slug actual es `admin`, no se puede cambiar.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $role = $this->route('role');
            if ($role instanceof \App\Models\Auth\Role && $role->slug === 'admin' && $this->has(key: 'slug') && $this->input(key: 'slug') !== 'admin') {
                $validator->errors()->add(
                    'slug',
                    'El rol "admin" no puede renombrarse (es crítico para el bypass de permisos).',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe estar en kebab-case (sólo minúsculas, números y guiones).',
            'slug.unique' => 'Ya existe un rol con ese slug.',
        ];
    }
}
