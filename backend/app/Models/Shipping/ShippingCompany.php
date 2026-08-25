<?php

declare(strict_types=1);

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa de mensajería o paquetería.
 *
 * Tabla: `shipping_companies` — relación 1:N con `shipments`.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 */
class ShippingCompany extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Envíos gestionados por esta empresa.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Filtra empresas de envío activas.
     *
     * @param  Builder<ShippingCompany>  $query  Consulta del modelo.
     * @return Builder<ShippingCompany>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(column: 'is_active', value: true);
    }
}
