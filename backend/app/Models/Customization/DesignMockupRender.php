<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Render de mockup generado para un diseño personalizado.
 *
 * Tabla: `design_mockup_renders` — relación N:1 con `custom_designs` y `product_mockups`.
 *
 * Almacena la imagen renderizada del diseño aplicado a un mockup
 * del producto, vista específica y metadatos del render.
 */
class DesignMockupRender extends Model
{
    protected $fillable = [
        'custom_design_id',
        'product_mockup_id',
        'view',
        'rendered_image_url',
        'status',
        'metadata',
        'rendered_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'rendered_at' => 'datetime',
    ];

    /**
     * Diseño personalizado al que pertenece este render.
     */
    public function customDesign(): BelongsTo
    {
        return $this->belongsTo(CustomDesign::class);
    }

    /**
     * Mockup del producto utilizado para el render.
     */
    public function productMockup(): BelongsTo
    {
        return $this->belongsTo(ProductMockup::class);
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeForView($query, string $view)
    {
        return $query->where('view', $view);
    }
}
