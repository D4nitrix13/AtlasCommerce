<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing\Admin\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/marketing/promotion-products — vincula un product a una promotion.
 */
class PromotionProductStoreRequest extends FormRequest
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
            'promotion_id' => ['required', 'integer', 'exists:promotions,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }
}
