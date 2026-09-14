<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/variants.
 *
 * Reglas:
 *  - product_id: requerido, existe en products.
 *  - name: requerido, max 160.
 *  - sku: único en product_variants.sku, max 120.
 *  - price_cents: int >= 0.
 *  - cost_cents: int >= 0.
 *  - barcode: opcional, único en product_variants.barcode (si viene).
 *  - is_active: bool, default true.
 */
class ProductVariantStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:160'],
            'sku' => ['required', 'string', 'min:1', 'max:120', 'unique:product_variants,sku'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:120', 'unique:product_variants,barcode'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'El producto indicado no existe.',
            'sku.unique' => 'Ya existe una variante con ese SKU.',
            'barcode.unique' => 'Ya existe una variante con ese código de barras.',
        ];
    }
}
