<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /api/account/addresses.
 *
 * El `customer_id` se obtiene del cliente autenticado en el controller
 * (no se acepta desde el body — el cliente sólo puede crear sus propias
 * direcciones).
 */
class CustomerAddressStoreRequest extends FormRequest
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
            'address_type' => ['required', 'string', 'in:shipping,billing,other'],
            'country' => ['required', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'detailed_address' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
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
