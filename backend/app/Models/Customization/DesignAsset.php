<?php

namespace App\Models\Customization;

use App\Models\Auth\User;
use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Archivo multimedia subido por un usuario para usar en diseños.
 *
 * Tabla: `design_assets` — relación N:1 con `users` y `customers`.
 *
 * Almacena imágenes, vectores y otros archivos con metadatos
 * como dimensiones, DPI, checksum y estado de procesamiento.
 */
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

    /**
     * Usuario que subió el archivo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cliente propietario del archivo.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Capas de diseño que utilizan este archivo.
     */
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
