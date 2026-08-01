<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor de atributo asignado a una variante de producto.
 *
 * Tabla: `attribute_variant` — relación N:1 con `product_variants`
 * y `attributes`.
 *
 * Tabla pivote con valor: permite asignar valores específicos a cada
 * variante (ej: variante "Rojo-M" → color = "Rojo", talla = "M").
 * La restricción UNIQUE garantiza que un atributo solo pueda
 * asignarse una vez por variante.
 */
class VariantAttributeValue extends Model
{
    protected $table = 'attribute_variant';

    protected $fillable = [
        'product_variant_id',
        'attribute_id',
        'value',
    ];

    /**
     * Variante a la que se asigna el atributo.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Atributo asignado.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class);
    }
}
