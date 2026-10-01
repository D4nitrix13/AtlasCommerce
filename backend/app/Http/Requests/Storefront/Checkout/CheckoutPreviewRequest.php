<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront\Checkout;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /storefront/checkout/preview — calcula totales sin crear orden.
 */
class CheckoutPreviewRequest extends FormRequest
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
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
        ];
    }
}
