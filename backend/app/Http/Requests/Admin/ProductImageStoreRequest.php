<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesVariantOwnership;
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
 *  - variant_id: int opcional. La variante tiene que ser DEL producto de
 *    la ruta — ver {@see ValidatesVariantOwnership}. Es la misma regla que
 *    aplica el upload por binario: las dos formas de crear la fila
 *    admiten la misma metadata.
 */
class ProductImageStoreRequest extends FormRequest
{
    use ValidatesVariantOwnership;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Exists>>
     */
    public function rules(): array
    {
        return [
            'image_url' => ['required', 'string', 'min:1', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'variant_id' => $this->variantIdRules(),
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
            ...$this->variantIdMessages(),
        ];
    }
}
