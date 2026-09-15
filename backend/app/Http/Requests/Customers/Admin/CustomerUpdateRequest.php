<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/customers/{customer}.
 *
 * Todos los campos son opcionales — se actualiza solo lo enviado.
 * `email` no se puede cambiar si el cliente ya está vinculado a un
 * user autenticado (controlado en el controller).
 */
class CustomerUpdateRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'min:1', 'max:80'],
            'last_name' => ['sometimes', 'string', 'min:1', 'max:80'],
            'email' => ['sometimes', 'string', 'email:rfc', 'max:255', 'unique:customers,email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'customer_type' => ['sometimes', 'string', 'in:retail,wholesale,vip'],
            'identity_document' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
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
        ];
    }
}
