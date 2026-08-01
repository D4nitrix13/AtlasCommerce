<?php

namespace App\Models\Taxes;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Impuesto aplicable a productos.
 *
 * Tabla: `product_tax` — pivote N:N entre `products` y `taxes`.
 */
class ProductTax extends Model
{
    protected $fillable = [
        'product_id',
        'tax_id',
    ];

    /**
     * Producto asociado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Impuesto asociado.
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
