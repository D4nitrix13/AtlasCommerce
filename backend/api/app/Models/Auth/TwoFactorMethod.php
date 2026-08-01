<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Método 2FA individual registrado por un usuario.
 *
 * Tabla: `two_factor_methods` — relación N:1 con `users`.
 *
 * Cada fila representa un método específico con su destino, estado de verificación
 * y secretos encriptados. Un usuario puede tener múltiples métodos activos.
 *
 * Para email_otp:
 *   - `destination`: correo electrónico del usuario.
 *   - `encrypted_secret`: no se usa (null).
 *
 * Para totp (fase futura):
 *   - `destination`: no se usa.
 *   - `encrypted_secret`: secreto TOTP encriptado con la clave de la aplicación.
 *
 * Para passkey (fase futura):
 *   - Los datos reales se almacenan en `passkey_credentials`.
 *   - Este registro sirve como referencia del método activo.
 *
 * El campo `verified` indica si el método pasó un proceso de verificación
 * (ej. confirmar que el código OTP llegó al correo correcto).
 */
class TwoFactorMethod extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'display_name',
        'destination',
        'encrypted_secret',
        'active',
        'verified',
        'verified_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Usuario propietario de este método 2FA.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
