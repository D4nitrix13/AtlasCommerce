<?php

declare(strict_types=1);

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Challenge OTP activo de un usuario.
 *
 * Representa cualquier one-time-password de un solo uso:
 *  - TOTP: el código de 6 dígitos generado por authenticator app al
 *    momento del login con 2FA habilitado.
 *  - email_otp: el código de 6 dígitos enviado por email durante
 *    el flujo de forgot-password (compete con `password_reset_tokens`).
 *  - recovery_code: el código alfanumérico generado cuando el user
 *    configura recovery codes (zuko, katara).
 *
 * Cada challenge es de un solo uso (`consumed_at` non-null = usado),
 * tiene TTL (`expires_at`), y tracking de attempts (rate limiting:
 * después de N intentos fallidos el challenge se invalida y se debe
 * generar uno nuevo).
 *
 * El code plain-text NUNCA se guarda — solo el hash bcrypt. Esto
 * significa que aunque la DB sea comprometida, los codes activos no
 * son recuperables.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $code_hash
 * @property array<string,mixed>|null $context
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $consumed_at
 * @property int $attempts
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $user
 */
class OtpChallenge extends Model
{
    public const TYPE_TOTP = 'totp';

    public const TYPE_EMAIL_OTP = 'email_otp';

    public const TYPE_RECOVERY_CODE = 'recovery_code';

    /** Máximo de intentos fallidos antes de invalidar el challenge. */
    public const MAX_ATTEMPTS = 5;

    protected $table = 'otp_challenges';

    protected $fillable = [
        'user_id',
        'type',
        'code_hash',
        'context',
        'expires_at',
        'consumed_at',
        'attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Relación al usuario dueño del challenge.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }

    /**
     * ¿Este challenge está vigente (no expirado, no consumido, sin
     * exceso de attempts)?
     */
    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    /**
     * ¿Está expirado?
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * ¿Excedió el máximo de intentos?
     */
    public function isExhausted(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }
}
