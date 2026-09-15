<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /api/account/addresses/{address}.
 *
 * Todos los campos son opcionales — sólo se actualiza lo enviado.
 */
class CustomerAddressUpdateRequest extends FormRequest
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
            'address_type' => ['sometimes', 'string', 'in:shipping,billing,other'],
            'country' => ['sometimes', 'string', 'max:80'],
            'department' => ['sometimes', 'nullable', 'string', 'max:80'],
            'municipality' => ['sometimes', 'nullable', 'string', 'max:120'],
            'detailed_address' => ['sometimes', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_type.in' => 'El tipo debe ser "shipping", "billing" u "other".',
        ];
    }
}
