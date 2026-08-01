<?php

namespace App\Models\Customization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignGraphic extends Model
{
    protected $fillable = [
        'name',
        'category',
        'tags',
        'type',
        'file_url',
        'preview_url',
        'license',
        'free',
        'active',
    ];

    protected $casts = [
        'tags' => 'array',
        'free' => 'boolean',
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

    public function scopeFree($query)
    {
        return $query->where('free', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
