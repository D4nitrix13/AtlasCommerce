<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de DELETE /account.
 *
 * Reglas:
 *  - token requerido, string, 32-128 chars (token URL-safe).
 *  - password requerido (re-auth obligatoria), mínimo 8 chars.
 */
class AccountDeletionConfirmRequest extends FormRequest
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
            'token' => ['required', 'string', 'min:32', 'max:128'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token de confirmación es obligatorio.',
            'token.min' => 'El token de confirmación no tiene el formato esperado.',
            'password.required' => 'Tenés que confirmar tu contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
