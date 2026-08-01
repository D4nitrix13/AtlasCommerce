<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductMockup extends Model
{
    protected $fillable = [
        'product_customization_id',
        'name',
        'view',
        'mockup_type',
        'base_image_url',
        'mask_image_url',
        'shadow_image_url',
        'render_settings',
        'primary',
        'active',
    ];

    protected $casts = [
        'render_settings' => 'array',
        'primary' => 'boolean',
        'active' => 'boolean',
    ];

    public function productCustomization(): BelongsTo
    {
        return $this->belongsTo(ProductCustomization::class);
    }

    public function renders(): HasMany
    {
        return $this->hasMany(DesignMockupRender::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('primary', true);
    }

    public function scopeForView($query, string $view)
    {
        return $query->where('view', $view);
    }
}
