<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/products/{product}/images.
 *
 * Reglas:
 *  - image_url: requerido, max 500 (URL de la imagen).
 *  - alt_text: opcional, max 160 (texto alternativo para a11y).
 *  - is_primary: bool opcional.
 *  - sort_order: int opcional >= 0. Si no se pasa, se asigna automáticamente
 *    al final (max + 1).
 */
class ProductImageStoreRequest extends FormRequest
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
            'image_url' => ['required', 'string', 'min:1', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image_url.required' => 'Tenés que indicar una URL de imagen.',
            'image_url.max' => 'La URL de la imagen no puede superar 500 caracteres.',
        ];
    }
}
