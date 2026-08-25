<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imagen de un producto asociada a una tienda específica.
 *
 * Tabla: `product_store_images` — relación N:1 con `product_stores`.
 *
 * Almacena la URL, texto alternativo y orden de visualización
 * de cada imagen personalizada para la tienda.
 */
class ProductStoreImage extends Model
{
    protected $fillable = [
        'product_store_id',
        'image_url',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Publicación del producto en la tienda.
     */
    public function productStore(): BelongsTo
    {
        return $this->belongsTo(ProductStore::class);
    }
}
