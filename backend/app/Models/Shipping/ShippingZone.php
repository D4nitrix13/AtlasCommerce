<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Builder;
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
 * @property string $base_cost
 * @property bool $is_active
 */
class ShippingZone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'base_cost',
        'is_active',
    ];

    protected $casts = [
        'base_cost' => 'decimal:2',
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
    public function scopeActive($query)
    {
        return $query->where(column: 'is_active', value: true);
    }
}
