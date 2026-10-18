<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Variante específica de un producto (talla, color, etc.).
 *
 * Tabla: `product_variants` — relación N:1 con `products` y 1:N
 * con `variant_attribute_values`.
 *
 * Cada variante tiene su propio SKU, precio y nivel de inventario.
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string $sku
 * @property int $price_cents
 * @property int $cost_cents
 * @property string|null $barcode
 * @property bool $is_active
 * @property string|null $finish_slug
 * @property string|null $finish_label
 * @property string|null $finish_hex
 * @property string $condition
 * @property int $stock
 * @property int|null $size_id
 */
class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price_cents',
        'cost_cents',
        'barcode',
        'is_active',
        'finish_slug',
        'finish_label',
        'finish_hex',
        'condition',
        'stock',
        'size_id',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'cost_cents' => 'integer',
        'is_active' => 'boolean',
        'stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'product_variant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Filtra variantes por slug de acabado (ej. 'rojo', 'negro', 'sunburst').
     *
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeByFinish(Builder $query, string $finishSlug): Builder
    {
        return $query->where('finish_slug', $finishSlug);
    }

    /**
     * Filtra variantes por condición (ej. 'nuevo', 'usado', 'ambos').
     *
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeByCondition(Builder $query, string $condition): Builder
    {
        return $query->where('condition', $condition);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeBySku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', $sku);
    }
}
