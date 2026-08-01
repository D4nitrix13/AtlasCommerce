<?php

namespace App\Models\Customization;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configuración de personalización para un producto o variante.
 *
 * Tabla: `product_customizations` — relación N:1 con `products` y `product_variants`.
 *
 * Indica si un producto es personalizable, el tipo de personalización
 * soportada, el editor utilizado y las instrucciones para el cliente.
 */
class ProductCustomization extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'customizable',
        'customization_type',
        'editor_type',
        'status',
        'instructions',
    ];

    protected $casts = [
        'customizable' => 'boolean',
    ];

    /**
     * Producto al que pertenece la configuración.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante específica del producto.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Áreas de impresión disponibles para esta configuración.
     */
    public function printAreas(): HasMany
    {
        return $this->hasMany(PrintArea::class);
    }

    /**
     * Mockups del producto para vista previa.
     */
    public function mockups(): HasMany
    {
        return $this->hasMany(ProductMockup::class);
    }

    /**
     * Diseños personalizados creados con esta configuración.
     */
    public function customDesigns(): HasMany
    {
        return $this->hasMany(CustomDesign::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCustomizable($query)
    {
        return $query->where('customizable', true);
    }
}
