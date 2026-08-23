<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /auth/reset-password.
 *
 * Reglas:
 *  - email requerido, formato email, máximo 255 chars.
 *  - method opcional: 'email' (default, code = 6 dígitos OTP) o
 *    'recovery_codes' (code = 10 chars alfanumérico, sin 0/O/1/I/L).
 *    El controller dispatcha al handler correspondiente y verifica que
 *    coincida con `users.preferred_recovery_method`.
 *  - challenge_token opcional (integer): cuando viene presente, el
 *    controller consume un OtpChallenge previamente emitido por
 *    /auth/recover-via-recovery-code o /auth/recover-via-totp. En ese
 *    modo `code` NO se exige (es un flow challenge_token de 2 pasos).
 *  - code: según method. Para 'email' son 6 dígitos; para
 *    'recovery_codes' son 10 chars del alfabeto permitido. Requerido
 *    salvo cuando se provee `challenge_token`.
 *  - password requerido, mínimo 12 caracteres, confirmado, con requisitos
 *    de complejidad (mayúscula, minúscula, número). Alineado con el
 *    frontend que muestra el passwordHint.
 *  - device_name opcional.
 *
 * El código se valida contra password_reset_tokens (email), vía
 * RecoveryCodeService (recovery_codes), o contra OtpChallenge
 * (challenge_token) en el controller; aquí solo validamos formato.
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
        $method = $this->input(key: 'method', default: 'email');
        $codeRule = $method === 'recovery_codes'
            ? ['required', 'string', 'regex:/^[A-HJ-KM-NP-Z2-9]{10}$/']
            : ['required', 'string', 'regex:/^[0-9]{6}$/'];

        $hasChallengeToken = $this->filled(key: 'challenge_token');

        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'method' => ['sometimes', 'string', 'in:email,recovery_codes'],
            'code' => $hasChallengeToken ? ['nullable', 'string'] : $codeRule,
            'challenge_token' => ['nullable', 'integer', 'min:1'],
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
            'method.in' => 'El método debe ser email o recovery_codes.',
            'code.required' => 'El código es obligatorio.',
            'code.regex' => 'El código no tiene el formato esperado para este método.',
            'challenge_token.integer' => 'El challenge token debe ser un identificador numérico.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'Su contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex' => 'Su contraseña debe tener una letra mayúscula, una letra minúscula y un número.',
        ];
    }
}
