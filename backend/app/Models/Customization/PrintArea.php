<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintArea extends Model
{
    protected $fillable = [
        'product_customization_id',
        'name',
        'code',
        'view',
        'width_inches',
        'height_inches',
        'width_px',
        'height_px',
        'dpi',
        'position_x',
        'position_y',
        'relative_width',
        'relative_height',
        'mask',
        'safe_zone',
        'active',
    ];

    protected $casts = [
        'width_inches' => 'decimal:2',
        'height_inches' => 'decimal:2',
        'position_x' => 'decimal:4',
        'position_y' => 'decimal:4',
        'relative_width' => 'decimal:4',
        'relative_height' => 'decimal:4',
        'mask' => 'array',
        'safe_zone' => 'array',
        'active' => 'boolean',
    ];

    public function productCustomization(): BelongsTo
    {
        return $this->belongsTo(ProductCustomization::class);
    }

    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForView($query, string $view)
    {
        return $query->where('view', $view);
    }
}
