<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Variante de un producto del catálogo.
 *
 * Tabla: `product_variants` — relación N:1 con `products` y N:N
 * con `attributes` a través de `attribute_variant`.
 *
 * Cada variante tiene su propio SKU, precio y costo independientes
 * del producto base, permitiendo control granular de inventario
 * y precios por variante.
 */
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant_sku',
        'variant_name',
        'price',
        'cost',
        'barcode',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Producto al que pertenece la variante.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Valores de atributos asignados a esta variante.
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class);
    }

    /**
     * Atributos asignados a esta variante.
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            related: ProductAttribute::class,
            table: 'attribute_variant',
        )->withPivot('value');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySku($query, string $sku)
    {
        return $query->where('variant_sku', $sku);
    }

    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }
}
