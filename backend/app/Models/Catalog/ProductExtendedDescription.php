<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloque de descripción extendida de un producto.
 *
 * Tabla: `product_extended_descriptions` — relación N:1 con
 * `products`. Cada bloque es un par `title` (heading) + `body`
 * (cuerpo) que la PDP renderiza después de la descripción corta
 * del modelo `Product`, permitiendo múltiples secciones temáticas
 * (ej. "The Les Paul, llevada al extremo" + bloque histórico).
 *
 * El orden de los bloques lo define `position` (ascendente).
 *
 * @property int $id
 * @property int $product_id
 * @property string $title
 * @property string $body
 * @property int $position
 */
class ProductExtendedDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'title',
        'body',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
