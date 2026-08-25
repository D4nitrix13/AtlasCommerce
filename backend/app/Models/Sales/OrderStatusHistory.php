<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de cambios de estado de un pedido.
 *
 * Tabla: `order_status_histories` — relación N:1 con `orders`.
 *
 * Registra cada transición de estado (pendiente, procesando,
 * enviado, entregado, cancelado) con timestamp y usuario responsable.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $user_id
 * @property string|null $previous_status
 * @property string $new_status
 * @property string|null $observation
 * @property Carbon $changed_at
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
