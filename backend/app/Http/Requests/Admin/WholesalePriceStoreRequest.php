<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/wholesale-prices.
 *
 * Aislamiento por tenant:
 *  El controller valida que `store_id` pertenezca a las tiendas
 *  accesibles del admin.
 *
 * Unicidad:
 *  `(product_id, store_id, min_qty)` es única por tabla. La validación
 *  aquí devuelve 422 con mensaje claro antes de que el DB rechace con
 *  SQLSTATE 23000.
 */
class WholesalePriceStoreRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'min_qty' => ['required', 'integer', 'min:1'],
            'price_cents' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'El producto no existe.',
            'store_id.exists' => 'La tienda no existe.',
            'min_qty.min' => 'La cantidad mínima debe ser al menos 1.',
            'price_cents.min' => 'El precio no puede ser negativo.',
        ];
    }
}
