<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zona geográfica para cálculo de costos de envío.
 *
 * Tabla: `shipping_zones` — relación 1:N con `shipments`.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $base_cost_cents
 * @property bool $is_active
 */
class ShippingZone extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'base_cost_cents',
        'is_active',
    ];

    protected $casts = [
        'base_cost_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Envíos asignados a esta zona.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Filtra zonas de envío activas.
     *
     * @param  Builder<ShippingZone>  $query  Consulta del modelo.
     * @return Builder<ShippingZone>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw('is_active = ?', [1]);
    }
}
