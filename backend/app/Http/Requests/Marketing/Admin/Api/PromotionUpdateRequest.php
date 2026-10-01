<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /admin/marketing/promotions/{promotion}.
 */
class PromotionUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['sometimes', 'string', 'in:percentage,fixed'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }
}
