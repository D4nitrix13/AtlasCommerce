<?php

namespace App\Services\Auth;

use App\Models\Auth\OtpChallenge;
use App\Models\Auth\User;
use App\Notifications\Auth\EmailOtpNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Servicio de autenticación OTP por correo electrónico.
 *
 * Orquesta el ciclo de vida completo de los códigos OTP de un solo uso:
 *
 * 1. **Generación**: código numérico de N dígitos usando `random_int()` (criba criptográfica).
 * 2. **Almacenamiento**: el código se guarda como hash bcrypt en `otp_challenges.code_hash`.
 *    El texto plano NUNCA se persiste en la base de datos.
 * 3. **Envío**: se envía por correo mediante `EmailOtpNotification`.
 * 4. **Verificación**: se compara con `Hash::check()` y se marca como usado.
 *
 * Seguridad:
 * - Cada reto tiene expiración configurable (default: 10 minutos).
 * - Cada reto tiene un máximo de intentos (default: 5) para prevenir fuerza bruta.
 * - Se registra IP y user-agent para auditoría.
 * - Se invalida el reto después de una verificación exitosa para evitar reutilización.
 */
class EmailOtpService
{
    /**
     * Genera un código OTP y lo envía al correo del usuario.
     *
     * Flujo:
     * 1. Lee configuración de `config/two_factor.php` (dígitos, expiración, intentos máximos).
     * 2. Genera el código numérico con `random_int()` (no `rand()` ni `mt_rand()`).
     * 3. Crea un `OtpChallenge` con el hash del código usando `Hash::make()`.
     * 4. Envía el código por correo mediante la notificación.
     * 5. Retorna el challenge para que el controlador pueda informar la expiración.
     *
     * @param  User  $user  Usuario destinatario del código OTP.
     * @param  string  $purpose  Propósito del reto: 'enable_2fa', 'login_2fa', 'change_2fa'.
     * @param  string|null  $ip  IP del cliente (para auditoría).
     * @param  string|null  $userAgent  User-Agent del cliente (para auditoría).
     * @return OtpChallenge El reto creado con la fecha de expiración y el destino.
     */
    public function send(
        User $user,
        string $purpose = 'enable_2fa',
        ?string $ip = null,
        ?string $userAgent = null,
    ): OtpChallenge {
        $digits = (int) config(key: 'two_factor.email_otp.digits', default: 6);
        $expiresMinutes = (int) config(key: 'two_factor.email_otp.expires_minutes', default: 10);
        $maxAttempts = (int) config(key: 'two_factor.email_otp.max_attempts', default: 5);

        $code = $this->generateNumericCode(digits: $digits);

        // El código OTP nunca se almacena en texto plano; solo se guarda su hash bcrypt.
        // Esto protege la integridad del código incluso si la BD es comprometida.
        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'method' => 'email_otp',
            'purpose' => $purpose,
            'code_hash' => Hash::make(value: $code),
            'destination' => $user->email,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'used' => false,
            'expires_at' => now()->addMinutes(value: $expiresMinutes),
            'request_ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        $user->notify(instance: new EmailOtpNotification(code: $code, expiresMinutes: $expiresMinutes));

        return $challenge;
    }

    /**
     * Verifica un código OTP ingresado por el usuario.
     *
     * Flujo de verificación:
     * 1. Busca el último reto activo (no usado) para el usuario y propósito.
     * 2. Si no hay reto, retorna false.
     * 3. Verifica que no esté expirado y que tenga intentos disponibles.
     * 4. Incrementa el contador de intentos ANTES de verificar (cuenta incluso intentos fallidos).
     * 5. Compara el código ingresado con el hash usando `Hash::check()`.
     * 6. Si es correcto, marca el reto como usado para evitar reutilización.
     *
     * Nota: el contador de intentos se incrementa antes de la verificación para
     * prevenir ataques de race condition donde múltiples intentos fallidos se
     * ejecuten simultáneamente antes de que el contador se actualice.
     *
     * @param  User  $user  Usuario que intenta verificar el código.
     * @param  string  $code  Código OTP ingresado por el usuario.
     * @param  string  $purpose  Propósito esperado del reto.
     * @return bool true si el código es válido y se verificó, false en caso contrario.
     */
    public function verify(User $user, string $code, string $purpose = 'enable_2fa'): bool
    {
        $challenge = OtpChallenge::query()
            ->where(column: 'user_id', operator: '=', value: $user->id, boolean: 'and')
            ->where(column: 'method', operator: '=', value: 'email_otp', boolean: 'and')
            ->where(column: 'purpose', operator: '=', value: $purpose, boolean: 'and')
            ->where(column: 'used', operator: '=', value: false, boolean: 'and')
            ->latest()
            ->first();

        if (! $challenge) {
            return false;
        }

        // Verificación de seguridad: expiración temporal y límite de intentos.
        if ($challenge->isExpired() || ! $challenge->hasAttemptsAvailable()) {
            return false;
        }

        // Se incrementa antes de verificar para prevenir race conditions.
        $challenge->increment(column: 'attempts', amount: 1);

        // Hash::check compara el código ingresado contra el hash bcrypt almacenado.
        if (! Hash::check(value: $code, hashedValue: $challenge->code_hash)) {
            return false;
        }

        // Se invalida el reto después de una verificación exitosa para evitar reutilización.
        // Un código OTP solo puede usarse una vez.
        $challenge->update([
            'used' => true,
            'used_at' => now(),
        ]);

        return true;
    }

    /**
     * Genera un código numérico de N dígitos usando `random_int()`.
     *
     * `random_int()` usa la fuente de entropía criptográfica del sistema operativo,
     * a diferencia de `rand()` o `mt_rand()` que no son aptos para seguridad.
     *
     * El rango [10^(N-1), 10^N - 1] garantiza exactamente N dígitos
     * (ej. para 6 dígitos: rango [100000, 999999]).
     */
    private function generateNumericCode(int $digits): string
    {
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int(min: $min, max: $max);
    }
}
