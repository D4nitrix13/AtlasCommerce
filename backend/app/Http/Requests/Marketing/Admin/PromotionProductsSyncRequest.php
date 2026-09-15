<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/promotions/{promotion}/products.
 *
 * Permite attach o detach:
 *  - `action`: 'attach' (default) | 'detach'
 *  - `product_ids`: array de IDs (deben existir en `products`).
 */
class PromotionProductsSyncRequest extends FormRequest
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
            'action' => ['nullable', 'string', 'in:attach,detach'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Tenés que indicar al menos un producto.',
            'product_ids.*.exists' => 'Uno o más productos no existen.',
        ];
    }
}
