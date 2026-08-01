<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor de atributo asignado a un producto.
 *
 * Tabla: `attribute_product` — relación N:1 con `products` y `attributes`.
 *
 * Tabla pivote con valor: permite asignar valores flexibles a cada
 * producto (ej: color = "Rojo", material = "Algodón").
 * La restricción UNIQUE garantiza que un atributo solo pueda
 * asignarse una vez por producto.
 */
class ProductAttributeValue extends Model
{
    protected $table = 'attribute_product';

    protected $fillable = [
        'product_id',
        'attribute_id',
        'value',
    ];

    /**
     * Producto al que se asigna el atributo.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Atributo asignado.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class);
    }
}
