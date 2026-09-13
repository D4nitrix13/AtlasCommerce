<?php

declare(strict_types=1);

namespace App\Http\Resources\Carts;

use App\Http\Resources\Catalog\ProductResource;
use App\Models\Settings\Currency;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para representar un artículo dentro del carrito de compras.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int|null $custom_design_id
 * @property string $cart_line_key
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $subtotal_cents
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->resolveCurrency();

        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'cart_line_key' => $this->cart_line_key,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'custom_design_id' => $this->custom_design_id,
            'quantity' => $this->quantity,
            'unit_price' => MoneyFormatter::payload($this->unit_price_cents, $currency),
            'subtotal' => MoneyFormatter::payload($this->subtotal_cents, $currency),
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_variant' => $this->whenLoaded('productVariant'),
            'custom_design' => $this->whenLoaded('customDesign'),
        ];
    }

    /**
     * Resuelve la moneda desde el cart relacionado.
     *
     * Si el cart no fue eager-loaded, devuelve null (la salida
     * mostrará `cents` sin formato).
     */
    private function resolveCurrency(): ?Currency
    {
        if (! $this->resource->relationLoaded('cart')) {
            return null;
        }

        $cart = $this->resource->getRelation('cart');

        if ($cart === null) {
            return null;
        }

        $cart->loadMissing('currency');

        return $cart->currency;
    }
}
