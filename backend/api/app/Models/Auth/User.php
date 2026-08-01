<?php

namespace App\Models\Auth;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo de usuario principal.
 *
 * Representa la tabla `users` y actúa como modelo base para todo el sistema
 * de autenticación. Incluye relaciones con el módulo de 2FA:
 *
 * - `twoFactorSetting`: configuración global 1:1 (habilitado, método preferido, obligatorio).
 * - `twoFactorMethods`: métodos individuales 2FA registrados (email_otp, totp, passkey).
 * - `otpChallenges`: retos OTP generados con código hasheado y control de intentos.
 * - `recoveryCodes`: códigos de recuperación de uso único (hash bcrypt).
 * - `passkeyCredentials`: credenciales WebAuthn para passkeys (fase futura).
 *
 * La autenticación de API se gestiona mediante Laravel Sanctum (trait HasApiTokens).
 * El cast automático `hashed` en `password` garantiza que la contraseña nunca se
 * almacene en texto plano.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * Atributos con cast automático.
     *
     * `email_verified_at` se trata como datetime para facilitar comparaciones.
     * `password` usa el cast `hashed` de Laravel que aplica Hash::make() automáticamente
     * al asignar un valor, evitando que la contraseña en texto plano se persista.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Configuración global de 2FA del usuario (relación 1:1).
     *
     * Contiene si 2FA está habilitado, el método preferido y si es obligatorio.
     * Se crea/actualiza cuando el usuario activa o desactiva 2FA.
     */
    public function twoFactorSetting(): HasOne
    {
        return $this->hasOne(TwoFactorSetting::class);
    }

    /**
     * Métodos 2FA individuales registrados (relación N:1).
     *
     * Un usuario puede tener múltiples métodos (email_otp, totp, passkey),
     * cada uno con su propio estado de verificación y activación.
     */
    public function twoFactorMethods(): HasMany
    {
        return $this->hasMany(TwoFactorMethod::class);
    }

    /**
     * Retos OTP generados para el usuario (relación N:1).
     *
     * Cada reto almacena el hash del código, control de intentos,
     * fecha de expiración y metadata de seguridad (IP, user-agent).
     */
    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class);
    }

    /**
     * Códigos de recuperación de uso único (relación N:1).
     *
     * Almacenados como hash bcrypt. Se muestran al usuario solo una vez
     * al generarlos y luego solo permanecen los hashes en la BD.
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    /**
     * Credenciales WebAuthn / Passkeys (relación N:1).
     *
     * Preparado para la fase futura de passkeys. Almacena la clave pública,
     * contador de firmas y metadata del authenticator.
     */
    public function passkeyCredentials(): HasMany
    {
        return $this->hasMany(PasskeyCredential::class);
    }
}
