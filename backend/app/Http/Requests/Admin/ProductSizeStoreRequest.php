<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/catalog/product-sizes.
 *
 * Reglas:
 *  - product_id: requerido, debe existir. El alcance por tenant se
 *    valida en el controller (404 si el producto no es accesible).
 *  - slug: requerido, kebab-case (^[a-z0-9]+(?:-[a-z0-9]+)*$), max 64 —
 *    misma convención que los assets.
 *  - label: requerido, max 64 (versión legible para UI).
 *  - value: nullable, numérico >= 0 (capacidad: 11 para una taza de 11 oz).
 *  - unit: nullable, una de oz/lb/ml/kg/cm/un. NULL cuando value es NULL.
 *  - position: nullable, entero >= 0 (default 0 en DB).
 *  - UNIQUE(product_id, slug) — validado a nivel DB. El controller
 *    captura el UniqueConstraintViolationException y responde 409 con
 *    mensaje claro.
 */
class ProductSizeStoreRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'label' => ['required', 'string', 'max:64'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:16', 'in:oz,lb,ml,kg,cm,un'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'El producto indicado no existe.',
            'slug.required' => 'Tenés que indicar un slug para la talla.',
            'slug.regex' => 'El slug debe ser kebab-case (minúsculas y guiones, ej. `11-oz`).',
            'label.required' => 'Tenés que indicar un label para la talla.',
            'value.numeric' => 'El valor debe ser numérico.',
            'value.min' => 'El valor no puede ser negativo.',
            'unit.in' => 'La unidad debe ser una de: oz, lb, ml, kg, cm, un.',
            'position.integer' => 'La posición debe ser un entero.',
            'position.min' => 'La posición no puede ser negativa.',
        ];
    }
}
