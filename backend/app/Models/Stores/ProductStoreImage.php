<?php

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function productStore(): BelongsTo
    {
        return $this->belongsTo(ProductStore::class);
    }
}
