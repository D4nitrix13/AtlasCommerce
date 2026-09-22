<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/brands/{brand}.
 *
 * name y slug únicos (excepto en el row actual).
 */
class BrandUpdateRequest extends FormRequest
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
        $route = $this->route('brand');
        $brandId = is_object($route) ? $route->id : (int) $route;

        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:120',
                Rule::unique(table: 'brands', column: 'name')->ignore($brandId),
            ],
            'slug' => [
                'sometimes',
                'string',
                'min:2',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(table: 'brands', column: 'slug')->ignore($brandId),
            ],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una marca con ese nombre.',
            'slug.regex' => 'El slug debe estar en kebab-case.',
            'slug.unique' => 'Ya existe una marca con ese slug.',
        ];
    }
}
