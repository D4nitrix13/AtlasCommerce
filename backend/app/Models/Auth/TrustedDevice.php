<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa un dispositivo de confianza registrado por un usuario.
 *
 * Tabla: `trusted_devices` — relación N:1 con `users`.
 *
 * Permite al usuario recordar dispositivos para omitir
 * la verificación de dos factores en futuros inicios de sesión.
 */
class TrustedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint_hash',
        'device_name',
        'last_ip',
        'last_user_agent',
        'trusted_at',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Usuario propietario del dispositivo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determina si el dispositivo está activo (no revocado y no expirado).
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
