<?php

namespace App\Models\Auth;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registra eventos de seguridad del sistema.
 *
 * Tabla: `security_events` — relación N:1 con `users`.
 *
 * Almacena eventos relevantes para la auditoría de seguridad,
 * como inicios de sesión sospechosos, cambios de contraseña,
 * y activación/desactivación de autenticación de dos factores.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $event_type
 * @property string $severity
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array|null $metadata
 * @property Carbon $event_at
 */
class SecurityEvent extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'event_type',
        'severity',
        'ip',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'event_at' => 'datetime',
    ];

    /**
     * Usuario asociado al evento (opcional — eventos sin usuario).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Filtra eventos por tipo específico (password_changed, login_failed, etc.).
     *
     * @param  Builder<SecurityEvent>  $query  Consulta del modelo.
     * @param  string  $type  Tipo de evento.
     * @return Builder<SecurityEvent>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where(column: 'event_type', value: $type);
    }

    /**
     * Filtra eventos por severidad específica (info, warning, critical).
     *
     * @param  Builder<SecurityEvent>  $query  Consulta del modelo.
     * @param  string  $severity  Nivel de severidad.
     * @return Builder<SecurityEvent>
     */
    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where(column: 'severity', value: $severity);
    }

    /**
     * Filtra eventos por usuario específico.
     *
     * @param  Builder<SecurityEvent>  $query  Consulta del modelo.
     * @param  int  $userId  Identificador del usuario.
     * @return Builder<SecurityEvent>
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where(column: 'user_id', value: $userId);
    }

    /**
     * Filtra eventos críticos.
     *
     * @param  Builder<SecurityEvent>  $query  Consulta del modelo.
     * @return Builder<SecurityEvent>
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where(column: 'severity', value: 'critical');
    }
}
