<?php

namespace App\Models\Sales;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de cambios de estado de un pedido.
 *
 * Tabla: `order_status_histories` — relación N:1 con `orders`.
 *
 * Registra cada transición de estado (pendiente, procesando,
 * enviado, entregado, cancelado) con timestamp y usuario responsable.
 */
class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'previous_status',
        'new_status',
        'observation',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Orden a la que pertenece el registro.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Usuario que realizó el cambio (opcional — cambios automáticos del sistema).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
