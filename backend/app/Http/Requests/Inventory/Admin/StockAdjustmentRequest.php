<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el ajuste de stock (PUT /admin/inventory/stock/{stock}).
 *
 * Body:
 *  - adjustment (int, required): delta a aplicar al stock.
 *  - reason (string, optional): motivo del ajuste.
 */
class StockAdjustmentRequest extends FormRequest
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
            'adjustment' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
