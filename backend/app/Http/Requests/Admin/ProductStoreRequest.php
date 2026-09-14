<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/products.
 *
 * Reglas:
 *  - name: requerido, max 160.
 *  - slug: kebab-case, único en `products.slug`.
 *  - sku: único en `products.sku`, max 120.
 *  - category_id: requerido, existe en `categories`.
 *  - brand_id: opcional, existe en `brands`.
 *  - currency_id: opcional, existe en `currencies` (si no se pasa, el
 *    admin debe asignarlo via UPDATE; el backend default actual no
 *    autocompleta).
 *  - description: opcional.
 *  - base_price_cents: int >= 0, default 0 si no se pasa.
 *  - cost_cents: int >= 0, default 0.
 *  - is_active: bool, default true.
 *  - published_at: nullable date.
 *  - store_ids: array opcional de IDs (visibility per tenant).
 *    Cada uno debe existir y ser accesible para el admin.
 */
class ProductStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'slug' => ['required', 'string', 'min:2', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug'],
            'sku' => ['required', 'string', 'min:1', 'max:120', 'unique:products,sku'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'base_price_cents' => ['nullable', 'integer', 'min:0'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe estar en kebab-case.',
            'slug.unique' => 'Ya existe un producto con ese slug.',
            'sku.unique' => 'Ya existe un producto con ese SKU.',
            'category_id.exists' => 'La categoría indicada no existe.',
            'brand_id.exists' => 'La marca indicada no existe.',
            'currency_id.exists' => 'La moneda indicada no existe.',
            'store_ids.*.exists' => 'Una o más tiendas no existen.',
        ];
    }
}
