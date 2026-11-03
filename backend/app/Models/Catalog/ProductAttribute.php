<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Atributo definido para productos del catálogo.
 *
 * Tabla: `attributes` — relación N:N con `products` a través de `attribute_product`
 * y N:N con `product_variants` a través de `attribute_variant`.
 *
 * Los atributos definen las características variantes (color, talla, material, etc.)
 * y pueden ser filtrables, requeridos o de tipo específico.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property bool $is_filterable
 * @property bool $is_required
 */
class ProductAttribute extends Model
{
    protected $table = 'attributes';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'unit',
        'options',
        'applies_to',
        'is_filterable',
        'is_required',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'is_required' => 'boolean',
        'options' => 'array',
        'applies_to' => 'array',
    ];

    /**
     * Productos que tienen asignado este atributo.
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id');
    }

    /**
     * Variantes que tienen valores de este atributo.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'attribute_id');
    }

    /**
     * Filtra atributos utilizables en filtros de búsqueda.
     *
     * @param  Builder<ProductAttribute>  $query  Consulta del modelo.
     * @return Builder<ProductAttribute>
     */
    public function scopeFilterable(Builder $query): Builder
    {
        // Args posicionales: ver note en Promotion::scopeActive.
        return $query->where('is_filterable', true);
    }

    /**
     * Filtra atributos requeridos para crear productos.
     *
     * @param  Builder<ProductAttribute>  $query  Consulta del modelo.
     * @return Builder<ProductAttribute>
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Filtra atributos por tipo específico (text, select, color, etc.).
     *
     * @param  Builder<ProductAttribute>  $query  Consulta del modelo.
     * @param  string  $type  Tipo de atributo.
     * @return Builder<ProductAttribute>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where(column: 'type', operator: '=', value: $type);
    }
}
