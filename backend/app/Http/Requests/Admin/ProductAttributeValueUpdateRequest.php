<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT/PATCH /admin/attribute-values/{attributeValue}.
 *
 * Solo se puede cambiar el `value` (no product_id ni attribute_id).
 * Si necesitás reasignar, borrá y creá uno nuevo.
 */
class ProductAttributeValueUpdateRequest extends FormRequest
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
            'value' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
