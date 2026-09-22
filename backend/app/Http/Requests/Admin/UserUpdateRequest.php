<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/users/{user}.
 *
 * Todos los campos son `sometimes` (sólo los enviados se actualizan).
 *
 * Protecciones adicionales (cross-field):
 *  - No podés cambiarte tu propio role_id (prevenir auto-demotion).
 *
 * Nota: el "no cambiar el role_id del último admin" se valida en el
 * controller (necesita consultar el store activo y contar admins).
 */
class UserUpdateRequest extends FormRequest
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
        $route = $this->route('user');
        $userId = is_object($route) ? $route->id : (int) $route;

        return [
            'email' => [
                'sometimes',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(table: 'users', column: 'email')->ignore($userId),
            ],
            // No exponemos cambio de password acá. Usar endpoint dedicado
            // /admin/users/{user}/password si se necesita en el futuro.
            'first_name' => ['sometimes', 'string', 'min:1', 'max:80'],
            'last_name' => ['sometimes', 'string', 'min:1', 'max:80'],
            'role_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'store_ids' => ['sometimes', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $target = $this->route('user');
            $current = $this->user();

            if (
                $target instanceof \App\Models\Auth\User
                && $current !== null
                && $target->id === $current->id
                && $this->has('role_id')
                && $this->input('role_id') !== $target->role_id
            ) {
                $validator->errors()->add(
                    'role_id',
                    'No podés cambiarte tu propio rol.',
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
            'email.unique' => 'Ya existe un usuario con ese email.',
            'role_id.exists' => 'El rol indicado no existe.',
            'store_ids.*.exists' => 'Una o más tiendas no existen.',
        ];
    }
}
