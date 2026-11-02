<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesVariantOwnership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT/PATCH /admin/products/{product}/images/{image}.
 *
 * Todos los campos opcionales (`sometimes`).
 *
 * `variant_id` es `sometimes` + `nullable`: el admin puede re-apuntar la
 * foto a otra variante del mismo producto, quitarla (null) o no tocar el
 * campo. Lo que NO puede es apuntarla a una variante ajena — ver
 * {@see ValidatesVariantOwnership}.
 */
class ProductImageUpdateRequest extends FormRequest
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
            'image_url' => ['sometimes', 'string', 'min:1', 'max:500'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:160'],
            'is_primary' => ['sometimes', 'boolean'],
            // `sometimes` + `nullable`: el admin puede promover una foto a
            // hover, bajarla de hover (`false`) o no tocar el campo.
            'is_hover' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'variant_id' => ['sometimes', ...$this->variantIdRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->variantIdMessages();
    }
}
