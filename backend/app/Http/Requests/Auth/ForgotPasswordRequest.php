<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /auth/forgot-password.
 *
 * Reglas:
 *  - email requerido, formato email, máximo 255 chars.
 *
 * La respuesta siempre es 200 OK con un mensaje genérico (seguridad:
 * no se revela si el email existe en el sistema). El rate limiting
 * se aplica via Laravel RateLimiter en el controller.
 */
class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener formato válido.',
        ];
    }
}