<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/products/{product}.
 *
 * Todos los campos son `sometimes` (sólo los enviados se actualizan).
 * El slug y sku deben seguir siendo únicos (excepto en el row actual).
 * No exponemos cambio de store_ids acá — usar
 * PUT /admin/products/{product}/stores para sync atómico.
 */
class ProductUpdateRequest extends FormRequest
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
        $route = $this->route('product');
        $productId = is_object($route) ? $route->id : (int) $route;

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:160'],
            'slug' => [
                'sometimes',
                'string',
                'min:2',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(table: 'products', column: 'slug')->ignore($productId),
            ],
            'sku' => [
                'sometimes',
                'string',
                'min:1',
                'max:120',
                Rule::unique(table: 'products', column: 'sku')->ignore($productId),
            ],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'currency_id' => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'base_price_cents' => ['sometimes', 'integer', 'min:0'],
            'cost_cents' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
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
        ];
    }
}
