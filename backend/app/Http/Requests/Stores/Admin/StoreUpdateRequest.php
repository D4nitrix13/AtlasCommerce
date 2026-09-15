<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/stores/{store}.
 *
 * Todos los campos son opcionales — sólo se actualiza lo enviado.
 * `slug`, `currency_id` NO son modificables acá (implican migraciones).
 */
class StoreUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:1', 'max:160'],
            'email' => ['sometimes', 'string', 'email:rfc', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'El color debe ser formato hexadecimal (#RRGGBB).',
            'secondary_color.regex' => 'El color debe ser formato hexadecimal (#RRGGBB).',
            'tax_rate.max' => 'La tasa de impuesto no puede ser mayor a 100%.',
        ];
    }
}
