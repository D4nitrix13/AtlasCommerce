<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración clave-valor de una tienda.
 *
 * Tabla: `store_settings` — relación N:1 con `stores`.
 *
 * Almacena pares de configuración agrupados por categoría,
 * con tipo de valor y visibilidad pública o privada.
 */
class StoreSetting extends Model
{
    protected $fillable = [
        'store_id',
        'setting_group',
        'setting_key',
        'setting_value',
        'value_type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Tienda a la que pertenece la configuración.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
