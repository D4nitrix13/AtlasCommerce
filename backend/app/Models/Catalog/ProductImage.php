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
 * @property int|null $variant_id
 * @property string $image_url
 * @property string|null $alt_text
 * @property int $sort_order
 * @property bool $is_primary
 * @property int|null $width
 */
class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'image_url',
        'alt_text',
        'sort_order',
        'is_primary',
        'width',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
        'width' => 'integer',
    ];

    /**
     * Producto al que pertenece la imagen.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante (acabado) al que pertenece la imagen.
     *
     * Nullable: el seeder histórico no persiste la asociación y los
     * productos single-variant sin assets en disco (4/19 según
     * diagnóstico Oct 2026) se quedan con `variant_id = NULL`. La PDP
     * degrada limpiamente: si no hay match por variant, muestra todas.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    /**
     * Filtra la imagen principal del producto.
     *
     * @param  Builder<ProductImage>  $query  Consulta del modelo.
     * @return Builder<ProductImage>
     */
    public function scopePrimary(Builder $query): Builder
    {
        // Args posicionales: ver note en Promotion::scopeActive.
        return $query->where('is_primary', true);
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
