<?php

declare(strict_types=1);

namespace App\Http\Requests\Carts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para actualizar la cantidad de un ítem ya existente.
 */
class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
