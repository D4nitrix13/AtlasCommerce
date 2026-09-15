<?php

declare(strict_types=1);

namespace App\Http\Requests\Tracking;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el query string de GET /tracking/{tracking_code}.
 *
 * La doble-factor (tracking + email de la orden) protege contra
 * fishing — alguien con sólo el código no puede consultar.
 */
class TrackingLookupRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Tenés que indicar el email de la orden.',
            'email.email' => 'El email no tiene formato válido.',
        ];
    }
}
