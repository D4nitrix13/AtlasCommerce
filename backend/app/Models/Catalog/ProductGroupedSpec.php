<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupador de specs por categoría (Cuerpo, Mástil, Electrónica, …).
 *
 * Tabla: `product_grouped_specs` — relación N:1 con `products` y 1:N
 * con `product_spec_values`. Cada grupo representa una sección del
 * acordeón de specs de la PDP estilo Gibson, y tiene 1..N filas
 * label/value dentro.
 *
 * `category` es el label visible del grupo (ej. "Cuerpo", "Mástil").
 * `position` define el orden del grupo en el acordeón.
 *
 * Orden canónico: `values()` ordenado por `position` ascendente.
 *
 * @property int $id
 * @property int $product_id
 * @property string $category
 * @property int $position
 */
class ProductGroupedSpec extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'category',
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
     * Filas label/value dentro del grupo, ordenadas por posición.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductSpecValue::class, 'grouped_spec_id')
            ->orderBy(value: 'position');
    }
}
