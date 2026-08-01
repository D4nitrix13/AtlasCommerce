<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Área de impresión definida para un producto personalizable.
 *
 * Tabla: `print_areas` — relación N:1 con `product_customizations` y 1:N con `design_layers`.
 *
 * Define las zonas donde el usuario puede colocar su diseño,
 * con dimensiones en pulgadas y píxeles, posición relativa
 * y configuraciones de máscara y zona segura.
 */
class PrintArea extends Model
{
    protected $fillable = [
        'product_customization_id',
        'name',
        'code',
        'view',
        'width_inches',
        'height_inches',
        'width_px',
        'height_px',
        'dpi',
        'position_x',
        'position_y',
        'relative_width',
        'relative_height',
        'mask',
        'safe_zone',
        'active',
    ];

    protected $casts = [
        'width_inches' => 'decimal:2',
        'height_inches' => 'decimal:2',
        'position_x' => 'decimal:4',
        'position_y' => 'decimal:4',
        'relative_width' => 'decimal:4',
        'relative_height' => 'decimal:4',
        'mask' => 'array',
        'safe_zone' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Configuración de personalización del producto.
     */
    public function productCustomization(): BelongsTo
    {
        return $this->belongsTo(ProductCustomization::class);
    }

    /**
     * Capas de diseño ubicadas en esta área.
     */
    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForView($query, string $view)
    {
        return $query->where('view', $view);
    }
}
