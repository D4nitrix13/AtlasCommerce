<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PATCH /admin/theme.
 *
 * Todos los campos son opcionales (sometimes) — el endpoint acepta
 * partial updates via `updateOrCreate`. La validación hex/email
 * garantiza coherencia antes de persistir.
 */
class ThemeUpdateRequest extends FormRequest
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
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'login_logo_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'favicon_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_hover_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['sometimes', 'nullable', 'email:rfc'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tokens' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.regex' => 'El campo :attribute debe ser un color hexadecimal (#RRGGBB).',
            '*.email' => 'El campo :attribute debe ser un email válido.',
        ];
    }
}
