<?php

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreDomain extends Model
{
    protected $fillable = [
        'store_id',
        'domain',
        'type',
        'base_path',
        'redirect_to_primary',
        'is_primary',
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'redirect_to_primary' => 'boolean',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
