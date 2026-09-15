<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/wholesale-prices/{wholesalePrice}.
 *
 * `product_id`, `store_id` y `min_qty` son inmutables en update: si
 * cambian, hay que borrar el tier viejo y crear uno nuevo (asigna
 * la unicidad correctamente). El controller valida esa invariante.
 */
class WholesalePriceUpdateRequest extends FormRequest
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
            'price_cents' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_cents.min' => 'El precio no puede ser negativo.',
        ];
    }
}
