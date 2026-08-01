<?php

namespace App\Models\Customization;

use App\Models\Auth\User;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Customers\Customer;
use App\Models\Sales\OrderCustomDesign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Diseño personalizado creado por un usuario.
 *
 * Tabla: `custom_designs` — relación N:1 con `customers`, `users`, `products` y `product_variants`.
 *
 * Almacena la configuración del lienzo, capas de diseño,
 * archivos de producción y metadatos de preview.
 * Puede asociarse a uno o más pedidos a través de `order_custom_designs`.
 */
class CustomDesign extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'product_id',
        'product_variant_id',
        'product_customization_id',
        'name',
        'status',
        'background_color',
        'canvas_width_px',
        'canvas_height_px',
        'canvas_settings',
        'preview_url',
        'production_file_url',
    ];

    protected $casts = [
        'canvas_settings' => 'array',
        'canvas_width_px' => 'integer',
        'canvas_height_px' => 'integer',
    ];

    /**
     * Cliente propietario del diseño.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Usuario que creó el diseño.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Producto al que pertenece el diseño.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante específica del producto asociada al diseño.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Configuración de personalización del producto.
     */
    public function productCustomization(): BelongsTo
    {
        return $this->belongsTo(ProductCustomization::class);
    }

    /**
     * Capas que componen el diseño.
     */
    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    /**
     * Renders de mockup generados para el diseño.
     */
    public function mockupRenders(): HasMany
    {
        return $this->hasMany(DesignMockupRender::class);
    }

    /**
     * Referencias del diseño en pedidos.
     */
    public function orderCustomDesigns(): HasMany
    {
        return $this->hasMany(OrderCustomDesign::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }
}
