<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT/PATCH /admin/products/{product}/images/{image}.
 *
 * Todos los campos opcionales (`sometimes`).
 */
class ProductImageUpdateRequest extends FormRequest
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
            'image_url' => ['sometimes', 'string', 'min:1', 'max:500'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:160'],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
