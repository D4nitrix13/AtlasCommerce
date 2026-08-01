<?php

namespace App\Models\Shipping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de seguimiento de un envío.
 *
 * Tabla: `shipment_trackings` — relación N:1 con `shipments`.
 *
 * Registra cada evento en la cadena de seguimiento:
 * recibido, en tránsito, en reparto, entregado, etc.
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

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('registered_at', 'desc');
    }
}
