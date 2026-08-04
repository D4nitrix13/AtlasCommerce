<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dominio configurado para una tienda.
 *
 * Tabla: `store_domains` — relación N:1 con `stores`.
 *
 * Almacena los dominios personalizados asociados a la tienda,
 * incluyendo el dominio principal y redirecciones.
 */
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

    /**
     * Tienda propietaria del dominio.
     *
     * @return BelongsTo<Store, StoreDomain>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
