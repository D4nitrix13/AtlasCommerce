<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Almacena tokens de recuperación de contraseña.
 *
 * Tabla: `password_reset_tokens` — relación N:1 con `users`.
 *
 * Cada token se genera cuando el usuario solicita restablecer su contraseña.
 * El token tiene un tiempo de expiración y se invalida después de su uso.
 */
class PasswordResetToken extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'token_hash',
        'expires_at',
        'used_at',
        'request_ip',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Usuario que solicitó el reset (opcional — sin autenticación).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determina si el token es válido (no usado y no expirado).
     */
    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
