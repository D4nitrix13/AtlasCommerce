<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /auth/reset-password.
 *
 * Reglas:
 *  - email requerido, formato email, máximo 255 chars.
 *  - code requerido, exactamente 6 dígitos (código OTP enviado por email).
 *  - password requerido, mínimo 12 caracteres, confirmado, con requisitos
 *    de complejidad (mayúscula, minúscula, número). Alineado con el
 *    frontend que muestra el passwordHint.
 *  - device_name opcional.
 *
 * El código se valida contra password_reset_tokens en el controller;
 * aquí solo validamos formato.
 */
class ResetPasswordRequest extends FormRequest
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
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:255',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
            ],
            'device_name' => ['nullable', 'string', 'max:120'],
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
            'code.required' => 'El código es obligatorio.',
            'code.regex' => 'El código debe ser de 6 dígitos.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'Su contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex' => 'Su contraseña debe tener una letra mayúscula, una letra minúscula y un número.',
        ];
    }
}