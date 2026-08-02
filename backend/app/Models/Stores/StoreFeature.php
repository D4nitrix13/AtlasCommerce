<?php

declare(strict_types=1);

namespace App\Models\Stores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature flag por tienda.
 *
 * Tabla: `store_features` — relación N:1 con `stores`.
 *
 * Cada registro indica si una feature específica (identificada por
 * `slug`) está activa o no para una tienda. La combinación
 * `(store_id, slug)` es única.
 *
 * Se consulta vía `Store::hasFeature(slug): bool`. El comando
 * `tenants:bootstrap` crea los registros desde el JSON de seed.
 *
 * Ejemplo de features (slugs):
 *   - wholesale_pricing
 *   - bulk_import
 *   - subscription_billing
 *   - instagram_shop
 *   - erp_sync
 *
 * @property int $id
 * @property int $store_id
 * @property string $slug
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class StoreFeature extends Model
{
    use HasFactory;

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'slug',
        'is_active',
    ];

    /**
     * Casts automaticos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Tienda a la que pertenece la feature.
     *
     * @return BelongsTo<Store, StoreFeature>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(related: Store::class, foreignKey: 'store_id');
    }
}
