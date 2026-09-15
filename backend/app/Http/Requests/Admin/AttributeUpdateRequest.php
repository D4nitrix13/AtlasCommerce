<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el body de PUT/PATCH /admin/attributes/{attribute}.
 *
 * Slug único excepto en el row actual.
 */
class AttributeUpdateRequest extends FormRequest
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
        $attributeId = (int) $this->route('attribute');

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'slug' => [
                'sometimes',
                'string',
                'min:2',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(table: 'attributes', column: 'slug')->ignore($attributeId),
            ],
            'type' => ['sometimes', 'string', 'min:1', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe estar en kebab-case.',
            'slug.unique' => 'Ya existe un atributo con ese slug.',
        ];
    }
}
