<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Models\Auth\User;
use App\Models\Concerns\BelongsToStore;
use App\Models\Stores\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificación enviada a un usuario.
 *
 * Tabla: `notifications` — relación N:1 con `users` y `stores`.
 *
 * Las notificaciones se clasifican por tipo (info, warning, error, success)
 * y pueden marcarse como leídas individualmente.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $store_id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property bool $is_read
 * @property Carbon|null $read_at
 */
class Notification extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'user_id',
        'store_id',
        'title',
        'message',
        'type',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Usuario destinatario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tienda asociada.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Marca la notificación como leída con la fecha y hora actual.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Filtra notificaciones no leídas.
     *
     * @param  Builder<Notification>  $query  Consulta del modelo.
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where(column: 'is_read', value: false);
    }

    /**
     * Filtra notificaciones por tipo específico.
     *
     * @param  Builder<Notification>  $query  Consulta del modelo.
     * @param  string  $type  Tipo de notificación (info, warning, error, success).
     * @return Builder<Notification>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where(column: 'type', value: $type);
    }

    /**
     * Filtra notificaciones por usuario específico.
     *
     * @param  Builder<Notification>  $query  Consulta del modelo.
     * @param  int  $userId  Identificador del usuario.
     * @return Builder<Notification>
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where(column: 'user_id', value: $userId);
    }

    /**
     * Filtra notificaciones por tienda específica.
     *
     * @param  Builder<Notification>  $query  Consulta del modelo.
     * @param  int  $storeId  Identificador de la tienda.
     * @return Builder<Notification>
     */
    public function scopeByStore(Builder $query, int $storeId): Builder
    {
        return $query->where(column: 'store_id', value: $storeId);
    }
}
