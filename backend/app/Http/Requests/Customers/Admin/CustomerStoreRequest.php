<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/customers.
 *
 * Crea un cliente manualmente — útil para que el admin registre
 * cuentas mayoristas / VIP sin pasar por el flujo de self-registration.
 *
 * El cliente creado NO tiene `user_id` (queda null hasta que el
 * cliente se registre y vincule su user).
 */
class CustomerStoreRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'min:1', 'max:80'],
            'last_name' => ['required', 'string', 'min:1', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'customer_type' => ['required', 'string', 'in:retail,wholesale,vip'],
            'identity_document' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'store_ids' => ['required', 'array', 'min:1'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un cliente con ese email.',
            'customer_type.in' => 'El tipo debe ser retail, wholesale o vip.',
            'store_ids.required' => 'El cliente debe estar asociado a al menos una tienda.',
            'store_ids.*.exists' => 'Una o más tiendas no existen.',
        ];
    }
}
