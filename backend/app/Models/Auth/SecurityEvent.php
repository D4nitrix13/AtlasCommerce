<?php

namespace App\Models\Auth;

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

    public function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }
}
