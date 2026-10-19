<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila label/value dentro de un grupo de specs.
 *
 * Tabla: `product_spec_values` — relación N:1 con `product_grouped_specs`.
 * Una fila es un par `label` (ej. "Madera") + `value` (ej. "Caoba")
 * que la PDP renderiza como una celda del acordeón de specs del
 * grupo padre.
 *
 * `value` se modela como `text` porque algunas specs pueden ser
 * largas (ej. "Burstbucker Pro + Burstbucker Pro Plus").
 *
 * @property int $id
 * @property int $grouped_spec_id
 * @property string $label
 * @property string $value
 * @property int $position
 */
class ProductSpecValue extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'grouped_spec_id',
        'label',
        'value',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function groupedSpec(): BelongsTo
    {
        return $this->belongsTo(ProductGroupedSpec::class, 'grouped_spec_id');
    }
}
