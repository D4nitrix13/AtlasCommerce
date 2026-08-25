<?php

declare(strict_types=1);

namespace App\Models\Auth;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Almacena tokens de recuperación de contraseña.
 *
 * Tabla: `password_reset_tokens` — relación N:1 con `users`.
 *
 * Cada token se genera cuando el usuario solicita restablecer su contraseña.
 * El token tiene un tiempo de expiración y se invalida después de su uso.
 * Nunca se almacena el token en texto plano — solo su hash.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $request_ip
 * @property string|null $user_agent
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
     *
     * @return bool True cuando el token puede usarse para restablecer la contraseña.
     */
    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Filtra tokens válidos (no usados y no expirados).
     *
     * @param  Builder<PasswordResetToken>  $query  Consulta del modelo.
     * @return Builder<PasswordResetToken>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Filtra tokens por correo electrónico específico.
     *
     * @param  Builder<PasswordResetToken>  $query  Consulta del modelo.
     * @param  string  $email  Correo electrónico.
     * @return Builder<PasswordResetToken>
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }
}
