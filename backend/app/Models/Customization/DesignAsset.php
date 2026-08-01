<?php

namespace App\Models\Customization;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DesignAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_id',
        'original_name',
        'file_name',
        'mime_type',
        'extension',
        'storage_disk',
        'file_path',
        'size_bytes',
        'width_px',
        'height_px',
        'dpi',
        'checksum',
        'source',
        'status',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width_px' => 'integer',
        'height_px' => 'integer',
        'dpi' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function designLayers(): HasMany
    {
        return $this->hasMany(DesignLayer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUploaded($query)
    {
        return $query->where('source', 'upload');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
