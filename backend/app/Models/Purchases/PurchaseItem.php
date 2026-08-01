<?php

namespace App\Models\Purchases;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo individual dentro de una orden de compra.
 *
 * Tabla: `purchase_items` — relación N:1 con `purchases`, `products`,
 * `product_variants`.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $unit_cost_cents
 * @property int $subtotal_cents
 */
class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_cost_cents',
        'subtotal_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost_cents' => 'integer',
        'subtotal_cents' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
