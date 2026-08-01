<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignMockupRender extends Model
{
    protected $fillable = [
        'custom_design_id',
        'product_mockup_id',
        'view',
        'rendered_image_url',
        'status',
        'metadata',
        'rendered_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'rendered_at' => 'datetime',
    ];

    public function customDesign(): BelongsTo
    {
        return $this->belongsTo(CustomDesign::class);
    }

    public function productMockup(): BelongsTo
    {
        return $this->belongsTo(ProductMockup::class);
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeForView($query, string $view)
    {
        return $query->where('view', $view);
    }
}
