<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gráfico o plantilla predefinida para usar en diseños.
 *
 * Tabla: `design_graphics` — relación 1:N con `design_layers`.
 *
 * Contiene gráficos SVG, PNG o vectoriales organizados por categoría,
 * con opciones de licencia gratuita o premium.
 */
class DesignGraphic extends Model
{
    protected $fillable = [
        'name',
        'category',
        'tags',
        'type',
        'file_url',
        'preview_url',
        'license',
        'free',
        'active',
    ];

    protected $casts = [
        'tags' => 'array',
        'free' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Capas de diseño que utilizan este gráfico.
     */
    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeFree($query)
    {
        return $query->where('free', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
