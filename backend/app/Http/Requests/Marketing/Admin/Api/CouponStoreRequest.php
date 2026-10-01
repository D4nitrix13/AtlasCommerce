<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/marketing/coupons — valida el body de creación.
 *
 * Mismo contrato que `CouponStoreRequest` legacy, expuesto bajo la nueva
 * ruta `/api/v1/admin/marketing/coupons` (namespace `Api`).
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
}
