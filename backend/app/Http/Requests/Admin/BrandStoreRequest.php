<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/brands.
 *
 * Reglas:
 *  - name: requerido, único en brands.name, max 120.
 *  - slug: kebab-case, único en brands.slug.
 *  - logo_url: opcional, URL válida.
 *  - description: opcional.
 *  - is_active: bool, default true.
 */
class BrandStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120', 'unique:brands,name'],
            'slug' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:brands,slug'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
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
