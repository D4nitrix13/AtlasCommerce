<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/products/{product}/stores.
 *
 * Reemplaza atómicamente el set de tiendas donde el producto está
 * visible. Vacío = limpia (product no se publica en ninguna tienda).
 */
class ProductStoresSyncRequest extends FormRequest
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
            'store_ids' => ['present', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_ids.required' => 'Tenés que enviar el array de tiendas.',
            'store_ids.*.exists' => 'Una o más tiendas no existen.',
        ];
    }
}
