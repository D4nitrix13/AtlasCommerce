<?php

namespace App\Models\Auth;

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

    public function scopeSuccessful($query)
    {
        return $query->where('successful', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('successful', false);
    }

    public function scopeSuspicious($query)
    {
        return $query->where('suspicious', true);
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }
}
