<?php

declare(strict_types=1);

namespace App\Models\Stores;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Publicación de un producto en una tienda específica.
 *
 * Tabla: `product_stores` — relación N:1 con `products` y `stores`.
 *
 * Permite personalizar la presentación del producto
 * en cada tienda: título, precio, descripción, slug y SEO.
 */
class ProductStore extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'slug',
        'public_title',
        'public_price',
        'public_description',
        'meta_title',
        'meta_description',
        'og_image_url',
        'is_visible',
        'is_featured',
        'sort_order',
        'published_at',
        'unpublished_at',
    ];

    protected $casts = [
        'public_price' => 'decimal:2',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'unpublished_at' => 'datetime',
    ];

    /**
     * Tienda donde se publica el producto.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Producto publicado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Imágenes del producto para esta tienda.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductStoreImage::class);
    }
}
