<?php

namespace App\Models\Returns;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo individual incluido en una solicitud de devolución.
 *
 * Tabla: `return_request_items` — relación N:1 con `return_requests`,
 * `products`, `product_variants`.
 *
 * @property int $id
 * @property int $return_request_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $quantity
 * @property int $amount_cents
 */
class ReturnRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_request_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'amount_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount_cents' => 'integer',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
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
