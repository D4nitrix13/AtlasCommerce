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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function productCustomization(): BelongsTo
    {
        return $this->belongsTo(ProductCustomization::class);
    }

    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function mockupRenders(): HasMany
    {
        return $this->hasMany(DesignMockupRender::class);
    }

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
