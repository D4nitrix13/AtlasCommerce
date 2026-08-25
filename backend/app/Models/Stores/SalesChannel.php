<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * Canal de venta configurado para una tienda.
 *
 * Tabla: `sales_channels` — relación N:1 con `stores`.
 *
 * Define los canales por los que se comercializan los productos:
 * tienda web, marketplace, redes sociales, etc.
 */
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Tienda propietaria del canal.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
