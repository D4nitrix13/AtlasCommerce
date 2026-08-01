<?php

namespace App\Models\System;

use App\Models\Auth\User;
use App\Models\Stores\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificación enviada a un usuario.
 *
 * Tabla: `notifications` — relación N:1 con `users` y `stores`.
 */
class Notification extends Model
{
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
     * Marca la notificación como leída.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
