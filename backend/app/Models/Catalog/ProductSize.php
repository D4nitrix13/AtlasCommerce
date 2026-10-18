<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Talla opcional asociada a un producto.
 *
 * Tabla: `product_sizes` — relación N:1 con `products` y 1:N con
 * `product_variants` (cuando una variante tiene FK `size_id`).
 *
 * Usada por tenants que venden productos con tallas (vestimenta,
 * calzado, joyería, instrumentos de cuerda como violín 4/4 vs 3/4).
 * Para guitarras eléctricas y la mayoría de productos del catálogo
 * `products.has_sizes = false` y la PDP no renderiza este grid.
 *
 * El slug se guarda URL-safe (ej. `4-4`, `s`, `m`, `l`). El label
 * es la versión legible para UI (ej. `4/4 (Full Size)`, `Small`).
 * `position` define el orden de renderizado en el grid (ascendente).
 *
 * @property int $id
 * @property int $product_id
 * @property string $slug
 * @property string $label
 * @property int $position
 */
class ProductSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'slug',
        'label',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Filtra tallas de un producto específico.
     *
     * @param  Builder<ProductSize>  $query
     * @return Builder<ProductSize>
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
