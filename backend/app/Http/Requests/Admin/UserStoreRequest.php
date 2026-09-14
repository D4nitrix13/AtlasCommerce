<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/users.
 *
 * Reglas:
 *  - email: único, formato email.
 *  - password: mín 8 chars (alineado con el cast `hashed` del User).
 *  - first_name, last_name: string requeridos.
 *  - role_id: opcional pero si viene debe existir en `roles`.
 *  - store_ids: array opcional de IDs; cada uno debe existir en `stores`.
 *  - is_active: bool opcional, default true.
 *
 * Aislamiento por tenant:
 *  El controller valida que todos los `store_ids` pertenezcan a las
 *  tiendas del admin que ejecuta la acción (sus `user_stores`).
 */
class UserStoreRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'first_name' => ['required', 'string', 'min:1', 'max:80'],
            'last_name' => ['required', 'string', 'min:1', 'max:80'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
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
