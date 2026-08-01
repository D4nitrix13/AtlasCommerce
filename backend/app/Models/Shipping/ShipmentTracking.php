<?php

namespace App\Models\Shipping;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de seguimiento de un envío.
 *
 * Tabla: `shipment_trackings` — relación N:1 con `shipments`.
 *
 * Registra cada evento en la cadena de seguimiento:
 * recibido, en tránsito, en reparto, entregado, etc.
 *
 * @property int $id
 * @property int $shipment_id
 * @property string $status
 * @property string|null $location
 * @property string|null $description
 * @property Carbon $registered_at
 */
class ShipmentTracking extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    /**
     * Envío al que pertenece el evento.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Filtra eventos por estado específico.
     *
     * @param  Builder<ShipmentTracking>  $query  Consulta del modelo.
     * @param  string  $status  Estado del evento de seguimiento.
     * @return Builder<ShipmentTracking>
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where(column: 'status', value: $status);
    }

    /**
     * Ordena eventos del más reciente al más antiguo.
     *
     * @param  Builder<ShipmentTracking>  $query  Consulta del modelo.
     * @return Builder<ShipmentTracking>
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy(column: 'registered_at', direction: 'desc');
    }
}
