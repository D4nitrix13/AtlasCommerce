<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el update de warehouse (PUT /admin/inventory/warehouses/{warehouse}).
 */
class WarehouseUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
