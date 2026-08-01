<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración global de 2FA por usuario.
 *
 * Tabla: `two_factor_settings` — relación 1:1 con `users`.
 *
 * Controla si 2FA está habilitado, cuál es el método preferido
 * (email_otp, totp, passkey) y si es obligatorio para el usuario.
 *
 * Se crea con `updateOrCreate` al verificar un código OTP exitosamente,
 * y se actualiza al desactivar 2FA (enabled: false, preferred_method: null).
 *
 * El campo `required` está preparado para uso futuro con middleware
 * que exija 2FA obligatorio para ciertos roles (ej. administradores).
 */
class TwoFactorSetting extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'preferred_method',
        'required',
        'enabled_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'required' => 'boolean',
        'enabled_at' => 'datetime',
    ];

    /**
     * Usuario propietario de esta configuración.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
