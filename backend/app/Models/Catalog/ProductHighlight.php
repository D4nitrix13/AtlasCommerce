<?php

declare(strict_types=1);

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Característica destacada de un producto (icono + label).
 *
 * Tabla: `product_highlights` — relación N:1 con `products`.
 * Cada highlight es un par `icon` (slug del SVG inline) + `label`
 * (texto legible bajo el icono). El conjunto ordenado por
 * `position` se renderiza en la PDP como grid visual estilo Gibson.
 *
 * `icon` se valida contra los slugs que conoce el frontend
 * (`premio`, `destello`, `regla`, `escudo`, `estrella`, `musica`)
 * pero la validación a nivel de dominio no se aplica acá para no
 * acoplarse al catálogo de iconos del frontend — el icon registry
 * vive en `ProductHighlights.astro` y puede crecer sin migraciones.
 *
 * @property int $id
 * @property int $product_id
 * @property string $icon
 * @property string $label
 * @property int $position
 */
class ProductHighlight extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'icon',
        'label',
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
