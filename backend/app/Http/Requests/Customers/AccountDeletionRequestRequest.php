<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /account/deletion-request.
 *
 * Hoy no requiere payload — la solicitud se genera para el cliente
 * autenticado a partir del token Sanctum. Se deja el FormRequest
 * para tener un punto de extensión estable cuando se sumen campos
 * (ej. motivo del borrado, feedback opcional).
 *
 * Reglas actuales: ninguna requerida.
 */
class AccountDeletionRequestRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
