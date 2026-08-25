<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customization\CustomDesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo individual dentro de un pedido.
 *
 * Tabla: `order_items` — relación N:1 con `orders`,
 * `products`, `product_variants`, `custom_designs`.
 *
 * Los snapshots `product_name_snapshot` y `product_sku_snapshot`
 * preservan la información del producto en el momento de la orden.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int|null $custom_design_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $discount_cents
 * @property int $subtotal_cents
 * @property string|null $product_name_snapshot
 * @property string|null $product_sku_snapshot
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'custom_design_id',
        'quantity',
        'unit_price_cents',
        'discount_cents',
        'subtotal_cents',
        'product_name_snapshot',
        'product_sku_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'discount_cents' => 'integer',
        'subtotal_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
