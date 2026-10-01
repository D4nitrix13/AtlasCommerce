<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront\Checkout;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /storefront/checkout/confirm/{orderId} — webhook payment stub.
 *
 * En producción este endpoint lo llama Stripe (o el PSP) cuando el pago
 * se acredita. En desarrollo aceptamos cualquier `payment_intent_id` y
 * marcamos la orden como pagada.
 */
class CheckoutConfirmRequest extends FormRequest
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
            'payment_intent_id' => ['required', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'in:succeeded,failed'],
        ];
    }
}
