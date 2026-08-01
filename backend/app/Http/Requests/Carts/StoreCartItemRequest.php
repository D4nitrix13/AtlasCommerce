<?php

declare(strict_types=1);

namespace App\Http\Requests\Carts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para agregar/actualizar un ítem en un carrito.
 *
 * Reglas:
 * - `product_id` debe existir en `products`.
 * - `product_variant_id` opcional (si se da, debe existir).
 * - `custom_design_id` opcional.
 * - `quantity` >= 1.
 *
 * El precio se toma del producto en el servidor; el cliente nunca lo
 * envía. El `cart_line_key` se deriva server-side a partir de
 * `product_id` + `product_variant_id` + `custom_design_id`.
 */
class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sanctum middleware ya filtra auth.
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'custom_design_id' => ['nullable', 'integer', 'exists:custom_designs,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * `cart_line_key` determinístico: mismas FKs ⇒ mismo key.
     *
     * Se usa el UNIQUE (cart_id, cart_line_key) para que agregar el
     * mismo producto dos veces actualice cantidad en lugar de duplicar.
     */
    public function cartLineKey(): string
    {
        return implode(':', [
            (string) $this->input('product_id'),
            (string) ($this->input('product_variant_id') ?? 0),
            (string) ($this->input('custom_design_id') ?? 0),
        ]);
    }
}
