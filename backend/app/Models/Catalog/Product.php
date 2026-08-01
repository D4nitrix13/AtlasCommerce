<?php

namespace App\Models\Catalog;

use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            Store::class,
            'product_stores',
        )->withPivot('is_visible');
    }
}
