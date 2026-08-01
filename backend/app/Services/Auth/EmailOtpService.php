<?php

namespace App\Services\Auth;

use App\Models\Auth\OtpChallenge;
use App\Models\Auth\User;
use App\Notifications\Auth\EmailOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Servicio para gestionar códigos OTP por correo electrónico.
 *
 * Maneja el ciclo de vida completo de un desafío OTP:
 * 1. **Generación**: crea un código numérico aleatorio.
 * 2. **Almacenamiento**: guarda el hash del código en la BD con metadatos
 *    (propósito, destino, expiración, intentos, IP, user agent).
 * 3. **Envío**: notifica al usuario por correo con el código en texto plano.
 * 4. **Verificación**: valida el código contra el hash, verifica expiración
 *    e intentos, y marca el desafío como usado.
 *
 * Configuración (desde config/two_factor.php):
 * - `digits`: longitud del código (default: 6).
 * - `expires_minutes`: tiempo de expiración en minutos (default: 10).
 * - `max_attempts`: máximo de intentos de verificación (default: 5).
 */
class EmailOtpService
{
    /**
     * Genera y envía un código OTP al correo del usuario.
     *
     * Crea un desafío en la BD con el hash del código, genera la notificación
     * y retorna el desafío para que el caller pueda acceder a metadatos
     * como `expires_at` y `destination`.
     *
     * @param  User  $user  Usuario que recibe el código.
     * @param  string  $purpose  Propósito del código (enable_2fa, login, reset, etc.).
     * @param  string|null  $ip  Dirección IP de la solicitud.
     * @param  string|null  $userAgent  User-Agent del navegador.
     */
    public function send(
        User $user,
        string $purpose = 'enable_2fa',
        ?string $ip = null,
        ?string $userAgent = null,
    ): OtpChallenge {
        $digits = (int) config('two_factor.email_otp.digits', default: 6);
        $expiresMinutes = (int) config('two_factor.email_otp.expires_minutes', default: 10);
        $maxAttempts = (int) config('two_factor.email_otp.max_attempts', default: 5);

        $code = $this->generateNumericCode($digits);

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'challenge_uuid' => Str::uuid(),
            'method' => 'email_otp',
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'destination' => $user->email,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'expires_at' => now()->addMinutes($expiresMinutes),
            'request_ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        $user->notify(new EmailOtpNotification(code: $code, expiresMinutes: $expiresMinutes));

        return $challenge;
    }

    /**
     * Verifica un código OTP contra el desafío más reciente del usuario.
     *
     * Busca el último desafío activo (no usado) que coincida con el usuario,
     * método y propósito. Valida expiración, intentos disponibles y coin cidencia
     * del hash. Si es correcto, marca el desafío como usado.
     *
     * @param  User  $user  Usuario que verifica el código.
     * @param  string  $code  Código OTP en texto plano.
     * @param  string  $purpose  Propósito del desafío a buscar.
     * @return bool true si el código es válido, false si no.
     */
    public function verify(User $user, string $code, string $purpose = 'enable_2fa'): bool
    {
        $challenge = OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('method', 'email_otp')
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $challenge) {
            return false;
        }

        if ($challenge->isExpired() || ! $challenge->hasAttemptsAvailable()) {
            return false;
        }

        $challenge->increment(column: 'attempts', amount: 1);

        if (! Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->update([
            'used_at' => now(),
        ]);

        return true;
    }

    /**
     * Genera un código numérico aleatorio de la longitud especificada.
     *
     * Asegura que el código tenga exactamente `$digits` dígitos,
     * rellenando con ceros a la izquierda si es necesario.
     */
    private function generateNumericCode(int $digits): string
    {
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
