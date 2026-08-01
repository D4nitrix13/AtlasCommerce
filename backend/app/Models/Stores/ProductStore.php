<?php

namespace App\Models\Stores;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductStoreImage::class);
    }
}
