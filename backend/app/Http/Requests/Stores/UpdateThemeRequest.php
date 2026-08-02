<?php

declare(strict_types=1);

namespace App\Http\Requests\Stores;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PATCH /api/admin/theme.
 *
 * Todos los campos son opcionales (se puede actualizar uno solo).
 * Pero si se envia un campo, debe respetar las constraints.
 *
 * Color hex formato #RRGGBB (7 chars incluyendo #).
 */
class UpdateThemeRequest extends FormRequest
{
    /**
     * Autorizacion: cualquier usuario autenticado puede editar el
     * theme de su propio store (resuelto por `store.domain` middleware).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validacion.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'short_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'login_logo_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'favicon_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_hover_color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tokens' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Mensajes de error en espanol.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'short_name.string' => 'El nombre corto debe ser texto.',
            'short_name.max' => 'El nombre corto no puede tener más de :max caracteres.',
            'accent_color.regex' => 'El color de acento debe ser un hex válido (#RRGGBB).',
            'accent_hover_color.regex' => 'El color hover debe ser un hex válido (#RRGGBB).',
            'primary_color.regex' => 'El color primario debe ser un hex válido (#RRGGBB).',
            'support_email.email' => 'El email de soporte debe ser un email válido.',
        ];
    }
}
