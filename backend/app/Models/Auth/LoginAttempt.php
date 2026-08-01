<?php

namespace App\Models\Auth;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registra intentos de inicio de sesión del usuario.
 *
 * Tabla: `login_attempts` — relación N:1 con `users`.
 *
 * Almacena cada intento de acceso con su resultado (exitoso/fallido),
 * dirección IP, user agent y timestamp. Utilizable para detectar
 * ataques de fuerza bruta y bloquear cuentas temporalmente.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property bool $successful
 * @property string|null $failure_reason
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $country
 * @property string|null $city
 * @property bool $suspicious
 * @property Carbon $attempted_at
 */
class LoginAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'successful',
        'failure_reason',
        'ip',
        'user_agent',
        'country',
        'city',
        'suspicious',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'suspicious' => 'boolean',
        'attempted_at' => 'datetime',
    ];

    /**
     * Usuario asociado al intento (opcional — email no registrado).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Filtra intentos exitosos.
     *
     * @param  Builder<LoginAttempt>  $query  Consulta del modelo.
     * @return Builder<LoginAttempt>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where(column: 'successful', value: true);
    }

    /**
     * Filtra intentos fallidos.
     *
     * @param  Builder<LoginAttempt>  $query  Consulta del modelo.
     * @return Builder<LoginAttempt>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where(column: 'successful', value: false);
    }

    /**
     * Filtra intentos marcados como sospechosos.
     *
     * @param  Builder<LoginAttempt>  $query  Consulta del modelo.
     * @return Builder<LoginAttempt>
     */
    public function scopeSuspicious(Builder $query): Builder
    {
        return $query->where(column: 'suspicious', value: true);
    }

    /**
     * Filtra intentos por correo electrónico específico.
     *
     * @param  Builder<LoginAttempt>  $query  Consulta del modelo.
     * @param  string  $email  Correo electrónico.
     * @return Builder<LoginAttempt>
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where(column: 'email', value: $email);
    }

    /**
     * Filtra intentos por dirección IP específica.
     *
     * @param  Builder<LoginAttempt>  $query  Consulta del modelo.
     * @param  string  $ip  Dirección IP.
     * @return Builder<LoginAttempt>
     */
    public function scopeByIp(Builder $query, string $ip): Builder
    {
        return $query->where(column: 'ip', value: $ip);
    }
}
