<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fuente tipográfica disponible para el editor de diseños.
 *
 * Tabla: `design_fonts` — relación 1:N con `design_layers`.
 *
 * Almacena la información de cada fuente: nombre, familia,
 * estilo, peso, ruta del archivo y licencia.
 */
class DesignFont extends Model
{
    protected $fillable = [
        'name',
        'family',
        'style',
        'weight',
        'file_path',
        'license',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Capas de diseño que utilizan esta fuente.
     */
    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
