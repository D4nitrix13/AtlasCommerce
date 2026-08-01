<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignLayer extends Model
{
    protected $fillable = [
        'custom_design_id',
        'print_area_id',
        'design_asset_id',
        'design_font_id',
        'design_graphic_id',
        'layer_type',
        'name',
        'text_content',
        'color',
        'background_color',
        'alignment',
        'position_x',
        'position_y',
        'width',
        'height',
        'scale_x',
        'scale_y',
        'rotation',
        'opacity',
        'z_index',
        'locked',
        'visible',
        'styles',
        'effects',
    ];

    protected $casts = [
        'position_x' => 'decimal:4',
        'position_y' => 'decimal:4',
        'width' => 'decimal:4',
        'height' => 'decimal:4',
        'scale_x' => 'decimal:4',
        'scale_y' => 'decimal:4',
        'rotation' => 'decimal:3',
        'opacity' => 'decimal:4',
        'z_index' => 'integer',
        'locked' => 'boolean',
        'visible' => 'boolean',
        'styles' => 'array',
        'effects' => 'array',
    ];

    public function customDesign(): BelongsTo
    {
        return $this->belongsTo(CustomDesign::class);
    }

    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }

    public function designAsset(): BelongsTo
    {
        return $this->belongsTo(DesignAsset::class);
    }

    public function designFont(): BelongsTo
    {
        return $this->belongsTo(DesignFont::class);
    }

    public function designGraphic(): BelongsTo
    {
        return $this->belongsTo(DesignGraphic::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    public function scopeForPrintArea($query, int $printAreaId)
    {
        return $query->where('print_area_id', $printAreaId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('z_index');
    }
}
