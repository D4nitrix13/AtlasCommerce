<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Atributo del catálogo de productos.
 *
 * Tabla: `attributes` — relación N:N con `products` a través de
 * `attribute_product` y con `product_variants` a través de
 * `attribute_variant`.
 *
 * Define los tipos de atributos disponibles (color, talla, material)
 * y si son filtrables o requeridos.
 */
class ProductAttribute extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'is_filterable',
        'is_required',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'is_required' => 'boolean',
    ];

    /**
     * Productos que tienen este atributo asignado.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'attribute_product',
        )->withPivot('value');
    }

    /**
     * Variantes que tienen este atributo asignado.
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            related: ProductVariant::class,
            table: 'attribute_variant',
        )->withPivot('value');
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
