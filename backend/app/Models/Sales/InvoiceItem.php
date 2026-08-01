<?php

namespace App\Models\Sales;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea individual de una factura.
 *
 * Tabla: `invoice_items` — relación N:1 con `invoices`,
 * `products`, `product_variants`.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $subtotal_cents
 * @property string|null $product_name_snapshot
 * @property string|null $product_sku_snapshot
 */
class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price_cents',
        'subtotal_cents',
        'product_name_snapshot',
        'product_sku_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'subtotal_cents' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
