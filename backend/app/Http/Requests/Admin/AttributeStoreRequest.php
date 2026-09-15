<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/attributes.
 *
 * Reglas:
 *  - name: requerido, max 100.
 *  - slug: kebab-case, único en attributes.slug.
 *  - type: requerido, max 50 (text, select, color, etc.).
 *  - description: opcional.
 *  - is_filterable: bool, default false.
 *  - is_required: bool, default false.
 */
class AttributeStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:attributes,slug'],
            'type' => ['required', 'string', 'min:1', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
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
