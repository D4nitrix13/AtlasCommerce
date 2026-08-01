<?php

namespace App\Models\Catalog;

use App\Models\Customization\ProductCustomization;
use App\Models\Inventory\Inventory;
use App\Models\Stores\ProductStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Producto principal del catálogo.
 *
 * Tabla: `products` — relación 1:N con `product_variants`,
 * `product_images`, `product_attribute_values` y N:N con `stores`
 * a través de `product_stores`.
 *
 * Entidad central del catálogo: almacena nombre, descripción,
 * precio base, SKU y estado de publicación.
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'sku',
        'base_price',
        'cost',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Categoría a la que pertenece el producto.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Marca del producto (opcional).
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Variantes del producto (talla, color, etc.).
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Imágenes del producto.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Valores de atributos asignados al producto.
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * Tiendas donde se publica el producto.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            related: ProductStore::class,
            table: 'product_stores',
        )->withPivot('is_visible');
    }

    /**
     * Configuración de personalización del producto.
     */
    public function customization(): HasMany
    {
        return $this->hasMany(ProductCustomization::class);
    }

    /**
     * Registros de inventario del producto.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeBySku($query, string $sku)
    {
        return $query->where('sku', $sku);
    }
}
