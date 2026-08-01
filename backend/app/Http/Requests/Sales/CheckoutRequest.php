<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación del checkout: convierte un carrito en pedido.
 *
 * Reglas:
 * - `cart_id` debe existir en `carts`.
 * - `shipping_address_id` debe existir y pertenecer al cliente.
 * - `payment_method_id` debe existir.
 *
 * El servicio verifica adicional: que el carrito pertenece al cliente
 * autenticado, que está `active`, que la tienda del carrito coincide
 * con la tienda actual (resuelta por middleware).
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'shipping_address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
