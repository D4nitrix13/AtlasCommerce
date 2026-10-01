<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront\Checkout;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /storefront/checkout/place — crea una orden real a partir del cart.
 */
class CheckoutPlaceRequest extends FormRequest
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
            'shipping_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_intent_id' => ['nullable', 'string', 'max:200'],
        ];
    }
}
