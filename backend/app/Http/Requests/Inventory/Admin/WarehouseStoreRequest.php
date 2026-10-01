<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros para GET /admin/inventory/warehouses.
 */
class WarehouseStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
