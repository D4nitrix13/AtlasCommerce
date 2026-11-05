<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use App\Enums\ProductRelationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación curada entre dos productos del catálogo.
 *
 * Tabla: `product_relations` — N:1 con `products` (origen y destino).
 * Ver {@see ProductRelationType} para los rieles que consumen estas
 * filas en la PDP.
 *
 * @property int $id
 * @property int $product_id
 * @property int $related_product_id
 * @property string $type
 * @property int $position
 */
class ProductRelation extends Model
{
    protected $fillable = [
        'product_id',
        'related_product_id',
        'type',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }

    /**
     * Filtra por riel de recomendación, ordenado por posición.
     *
     * @param  Builder<ProductRelation>  $query
     * @return Builder<ProductRelation>
     */
    public function scopeOfType(Builder $query, ProductRelationType|string $type): Builder
    {
        $value = $type instanceof ProductRelationType ? $type->value : $type;

        return $query
            ->where('type', $value)
            ->orderBy('position');
    }
}
