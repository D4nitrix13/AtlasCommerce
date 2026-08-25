<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen asociada a un producto.
 *
 * Tabla: `product_images` — relación N:1 con `products`.
 *
 * Cada producto puede tener múltiples imágenes, una de las cuales
 * se marca como principal para mostrar en listados y búsquedas.
 *
 * @property int $id
 * @property int $product_id
 * @property string $url
 * @property string|null $alt_text
 * @property int $sort_order
 * @property bool $is_primary
 */
class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Producto al que pertenece la imagen.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Filtra la imagen principal del producto.
     *
     * @param  Builder<ProductImage>  $query  Consulta del modelo.
     * @return Builder<ProductImage>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where(column: 'is_primary', value: true);
    }

    /**
     * Filtra imágenes ordenadas por su posición.
     *
     * @param  Builder<ProductImage>  $query  Consulta del modelo.
     * @return Builder<ProductImage>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy(column: 'sort_order', direction: 'asc');
    }
}
