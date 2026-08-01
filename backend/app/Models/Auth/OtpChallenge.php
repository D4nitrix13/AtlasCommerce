<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de retos OTP (One-Time Password).
 *
 * Tabla: `otp_challenges` — relación N:1 con `users`.
 *
 * Almacena cada intento de verificación OTP con:
 * - Código hasheado con bcrypt (`code_hash`): el código en texto plano nunca se persiste.
 * - Control de intentos (`attempts` / `max_attempts`): previene fuerza bruta.
 * - Expiración temporal (`expires_at`): el código tiene vida limitada.
 * - Metadata de seguridad (`request_ip`, `user_agent`): auditoría y detección de fraude.
 * - Propósito (`purpose`): permite distinguir entre login_2fa, enable_2fa, change_2fa.
 *
 * Flujo de vida:
 * 1. Se crea con `used_at: null` y `attempts: 0`.
 * 2. Cada intento de verificación incrementa `attempts`.
 * 3. Si el código es correcto, se marca `used_at: now()`.
 * 4. Si se agotan los intentos o expira, el reto queda inutilizable.
 */
class OtpChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_uuid',
        'method',
        'purpose',
        'code_hash',
        'destination',
        'attempts',
        'max_attempts',
        'resend_count',
        'last_sent_at',
        'expires_at',
        'used_at',
        'locked_until',
        'request_ip',
        'user_agent',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'resend_count' => 'integer',
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    /**
     * Usuario que solicitó este reto OTP.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Indica si el reto ha expirado según `expires_at`.
     *
     * Se compara la fecha actual con la fecha de expiración usando
     * Carbon para un cálculo preciso de zona horaria.
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    /**
     * Indica si aún quedan intentos disponibles para verificación.
     *
     * Si `attempts` alcanza `max_attempts`, el reto se considera
     * bloqueado independientemente de si el código es correcto.
     */
    public function hasAttemptsAvailable(): bool
    {
        return $this->attempts < $this->max_attempts;
    }
}
