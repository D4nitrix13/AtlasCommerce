<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de PUT /admin/coupons/{coupon}.
 *
 * `code` es inmutable para preservar trazabilidad de usos existentes.
 * Si necesitás cambiarlo, borrá el cupón y creá uno nuevo.
 */
class CouponUpdateRequest extends FormRequest
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
            'discount_type' => ['sometimes', 'string', 'in:percentage,fixed'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'minimum_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discount_type.in' => 'El tipo debe ser "percentage" o "fixed".',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
