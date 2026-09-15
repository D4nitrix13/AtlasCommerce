<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enlace a red social de una tienda.
 *
 * Tabla: `store_social_links` — relación N:1 con `stores`.
 *
 * Almacena las URLs de las redes sociales de la tienda
 * con su plataforma, nombre de display y orden de visualización.
 */
class StoreSocialLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'platform',
        'url',
        'display_name',
        'icon_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Tienda propietaria del enlace.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
