<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT/PATCH /admin/catalog/product-sizes/{productSize}.
 *
 * Se pueden mutar slug/label/value/unit/position (NO product_id: si
 * necesitás reasignar la talla a otro producto, borrá y creá una nueva).
 *
 * Todos los campos son `sometimes`: el update acepta payloads parciales
 * (ej. solo `value` + `unit`). `slug` y `label`, si vienen, son
 * requeridos — no se permiten vacíos.
 *
 * UNIQUE(product_id, slug) — validado a nivel DB. Si el update cambia el
 * slug a uno ya usado por otra talla del mismo producto, el controller
 * captura el UniqueConstraintViolationException y responde 409.
 */
class ProductSizeUpdateRequest extends FormRequest
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
            'slug' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'label' => ['sometimes', 'required', 'string', 'max:64'],
            'value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:16', 'in:oz,lb,ml,kg,cm,un'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
