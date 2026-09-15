<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/attribute-values.
 *
 * Reglas:
 *  - product_id: requerido, debe existir y estar en el tenant del admin.
 *  - attribute_id: requerido, debe existir.
 *  - value: requerido, max 255.
 *  - UNIQUE(product_id, attribute_id) — validado a nivel DB (constraint
 *    UNIQUE en attribute_product). Si el admin intenta asignar el mismo
 *    atributo dos veces al mismo producto, devuelve 500 con mensaje
 *    claro. Esto es OK porque es un error de programación, no de UX.
 */
class ProductAttributeValueStoreRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'value' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'El producto indicado no existe.',
            'attribute_id.exists' => 'El atributo indicado no existe.',
            'value.required' => 'Tenés que indicar un valor para el atributo.',
        ];
    }
}
