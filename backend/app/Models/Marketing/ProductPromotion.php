<?php

declare(strict_types=1);

namespace App\Models\Marketing;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asociación entre una promoción y un producto.
 *
 * Tabla: `product_promotion` — pivote N:N entre `promotions` y `products`.
 *
 * @property int $id
 * @property int $product_id
 * @property int $promotion_id
 */
class ProductPromotion extends Model
{
    protected $fillable = [
        'product_id',
        'promotion_id',
    ];

    /**
     * Producto asociado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Promoción asociada.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
