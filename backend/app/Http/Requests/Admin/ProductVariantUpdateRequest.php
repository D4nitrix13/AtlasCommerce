<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/variants/{variant}.
 *
 * Todos los campos son `sometimes`. SKU y barcode únicos (excepto
 * el row actual).
 */
class ProductVariantUpdateRequest extends FormRequest
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
        $variantId = (int) $this->route('variant');

        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:160'],
            'sku' => [
                'sometimes',
                'string',
                'min:1',
                'max:120',
                Rule::unique(table: 'product_variants', column: 'sku')->ignore($variantId),
            ],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'cost_cents' => ['sometimes', 'integer', 'min:0'],
            'barcode' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                Rule::unique(table: 'product_variants', column: 'barcode')->ignore($variantId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'Ya existe una variante con ese SKU.',
            'barcode.unique' => 'Ya existe una variante con ese código de barras.',
        ];
    }
}
