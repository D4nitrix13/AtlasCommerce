<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Valida el body de POST /api/v1/storefront/auth/register (CA.2).
 *
 * Reglas:
 *  - email requerido, único en users, formato email rfc, max 255.
 *  - password requerido, min 8 chars (alineado con creación de users).
 *  - password_confirmation requerido, debe coincidir con password.
 *  - first_name, last_name requeridos, min 2 max 200.
 *  - phone opcional, max 50 (sin formato estricto — E.164 si el user
 *    lo quiere, sino local).
 *  - customer_type opcional, default 'retail'. Valores válidos
 *    definidos en rule.
 *
 * El endpoint es público (no requiere auth). El rate limiting se
 * aplica via throttle middleware en la ruta (10/min por IP).
 */
class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->max(255)],
            'password_confirmation' => ['required', 'same:password'],
            'first_name' => ['required', 'string', 'min:2', 'max:200'],
            'last_name' => ['required', 'string', 'min:2', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', 'string', 'in:retail,wholesale,vip'],
        ];
    }
}
