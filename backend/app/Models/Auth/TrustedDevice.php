<?php

namespace App\Models\Auth;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa un dispositivo de confianza registrado por un usuario.
 *
 * Tabla: `trusted_devices` — relación N:1 con `users`.
 *
 * Permite al usuario recordar dispositivos para omitir
 * la verificación de dos factores en futuros inicios de sesión.
 * Los dispositivos pueden expirar o ser revocados manualmente.
 *
 * @property int $id
 * @property int $user_id
 * @property string $fingerprint_hash
 * @property string|null $device_name
 * @property string|null $last_ip
 * @property string|null $last_user_agent
 * @property Carbon|null $trusted_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
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
     *
     * @return bool True cuando el dispositivo puede usarse para autenticación.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Filtra dispositivos activos (no revocados y no expirados).
     *
     * @param  Builder<TrustedDevice>  $query  Consulta del modelo.
     * @return Builder<TrustedDevice>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull(column: 'revoked_at')
            ->where(function ($q) {
                $q->whereNull(column: 'expires_at')
                    ->orWhere(column: 'expires_at', operator: '>', value: now());
            });
    }

    /**
     * Filtra dispositivos revocados.
     *
     * @param  Builder<TrustedDevice>  $query  Consulta del modelo.
     * @return Builder<TrustedDevice>
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull(column: 'revoked_at');
    }

    /**
     * Filtra dispositivos por usuario específico.
     *
     * @param  Builder<TrustedDevice>  $query  Consulta del modelo.
     * @param  int  $userId  Identificador del usuario.
     * @return Builder<TrustedDevice>
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where(column: 'user_id', value: $userId);
    }
}
