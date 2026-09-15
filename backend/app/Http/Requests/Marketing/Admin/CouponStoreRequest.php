<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el body de POST /admin/coupons.
 *
 * `code` se normaliza a UPPERCASE antes de validar (Laravel no hace
 * transformación por sí solo). El descuento se guarda como decimal
 * (12,2) — para `fixed` se interpreta como monto en la moneda del
 * store; para `percentage` como porcentaje (0-100).
 */
class CouponStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper((string) $this->input('code')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Z0-9_-]+$/', 'unique:coupons,code'],
            'discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código sólo puede tener letras mayúsculas, números, guion y guion bajo.',
            'code.unique' => 'Ya existe un cupón con ese código.',
            'discount_type.in' => 'El tipo debe ser "percentage" o "fixed".',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
