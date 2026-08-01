<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial WebAuthn / Passkey para autenticación sin contraseña.
 *
 * Tabla: `passkey_credentials` — relación N:1 con `users`.
 *
 * Preparado para la fase futura de integración de passkeys.
 * Almacena la información necesaria para el protocolo WebAuthn:
 *
 * - `credential_id`: identificador único de la credencial (hasta 1024 bytes).
 * - `public_key`: clave pública del authenticator.
 * - `sign_count`: contador de firmas para detectar clonación de credenciales.
 * - `transports`: transportes soportados (usb, nfc, ble, internal) en formato JSON.
 * - `aaguid`: identificador del authenticator manufacturer.
 * - `device_name`: nombre legible del dispositivo para el usuario.
 *
 * Pendiente: integrar con `laravel/passkeys` o librería equivalente.
 */
class PasskeyCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
        'aaguid',
        'device_name',
        'last_used_at',
        'active',
    ];

    protected $casts = [
        'sign_count' => 'integer',
        'transports' => 'array',
        'last_used_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * Usuario propietario de esta credencial passkey.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
