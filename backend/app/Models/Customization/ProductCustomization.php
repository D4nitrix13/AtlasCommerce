<?php

namespace App\Models\Customization;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function printAreas(): HasMany
    {
        return $this->hasMany(PrintArea::class);
    }

    public function mockups(): HasMany
    {
        return $this->hasMany(ProductMockup::class);
    }

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
