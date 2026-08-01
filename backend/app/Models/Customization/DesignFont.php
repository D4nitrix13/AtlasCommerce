<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignFont extends Model
{
    protected $fillable = [
        'name',
        'family',
        'style',
        'weight',
        'file_path',
        'license',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
