<?php

namespace App\Models\Carts;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customization\CustomDesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo individual dentro del carrito de compras.
 *
 * Tabla: `cart_items` — relación N:1 con `carts`, `products`,
 * `product_variants`, `custom_designs`.
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
class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'custom_design_id',
        'cart_line_key',
        'quantity',
        'unit_price_cents',
        'subtotal_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'subtotal_cents' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function customDesign(): BelongsTo
    {
        return $this->belongsTo(CustomDesign::class);
    }
}
