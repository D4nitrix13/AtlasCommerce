<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mockup de producto para vista previa de diseños personalizados.
 *
 * Tabla: `product_mockups` — relación N:1 con `product_customizations` y 1:N con `design_mockup_renders`.
 *
 * Almacena las imágenes base, máscara y sombra de cada mockup,
 * junto con la configuración de renderizado para generar vistas previas.
 */
class ProductMockup extends Model
{
    protected $fillable = [
        'product_customization_id',
        'name',
        'view',
        'mockup_type',
        'base_image_url',
        'mask_image_url',
        'shadow_image_url',
        'render_settings',
        'primary',
        'active',
    ];

    protected $casts = [
        'render_settings' => 'array',
        'primary' => 'boolean',
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
     * Renders generados a partir de este mockup.
     */
    public function renders(): HasMany
    {
        return $this->hasMany(DesignMockupRender::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('primary', true);
    }

    public function scopeForView($query, string $view)
    {
        return $query->where('view', $view);
    }
}
