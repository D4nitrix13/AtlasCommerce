<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/categories.
 *
 * Reglas:
 *  - name: requerido, max 120.
 *  - slug: kebab-case, único en categories.slug.
 *  - parent_id: opcional, debe existir en categories, NO puede ser
 *    self (validado en withValidator porque en create aún no hay self).
 *  - description: opcional.
 *  - image_path: opcional.
 *  - is_active: bool, default true.
 */
class CategoryStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug debe estar en kebab-case.',
            'slug.unique' => 'Ya existe una categoría con ese slug.',
            'parent_id.exists' => 'La categoría padre indicada no existe.',
        ];
    }
}
