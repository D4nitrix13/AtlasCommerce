<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la cancelación de una orden.
 *
 * Solo el campo `observation` es opcional — la autorización (permiso
 * `sales.orders.cancel`) se valida vía middleware en la ruta. La
 * transición de estado y los bloqueos por shipments se validan en
 * `OrderService::cancel()`.
 */
class CancelOrderRequest extends FormRequest
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
            'observation' => ['nullable', 'string', 'max:500'],
        ];
    }
}
