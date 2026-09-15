<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailOtpRequestRequest;
use App\Http\Requests\Auth\EmailOtpVerifyRequest;
use App\Models\Auth\OtpChallenge;
use App\Models\Auth\User;
use App\Notifications\Auth\EmailOtpLoginNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login passwordless con OTP por email (RF-AUTH-007).
 *
 * Flujo:
 *   1. POST /auth/login/email-otp/request   { email }
 *      → genera código de 6 dígitos, hash bcrypt, guarda OtpChallenge,
 *        envía email con el código. Rate-limited.
 *   2. POST /auth/login/email-otp/verify    { email, code }
 *      → busca el challenge, valida código + expiración + attempts,
 *        marca consumido, emite Sanctum token.
 *
 * Reglas:
 *  - TTL del código: 10 minutos.
 *  - MAX_ATTEMPTS = 5 (heredado de OtpChallenge). Después de 5
 *    intentos fallidos, el challenge se invalida.
 *  - Code plain NUNCA se persiste (solo hash bcrypt en OtpChallenge).
 *  - Rate limiter dedicado para evitar spam de OTP requests.
 */
class EmailOtpController extends Controller
{
    private const TTL_MINUTES = 10;

    private const CODE_LENGTH = 6;

    public function request(EmailOtpRequestRequest $request): JsonResponse
    {
        $email = strtolower((string) $request->input('email'));

        // Rate limit por email — evita spam y enumeración.
        $key = 'email-otp-request:'.sha1($email);
        if (RateLimiter::tooManyAttempts(key: $key, maxAttempts: 3)) {
            return response()->json(
                data: ['message' => 'Demasiados pedidos. Esperá unos minutos.'],
                status: 429,
            );
        }
        RateLimiter::hit(key: $key, decaySeconds: 60);

        $user = User::query()->where('email', $email)->first();

        // Anti-enumeration: misma respuesta exista o no el email.
        // Si el email existe y user está activo, genera challenge + envía.
        if ($user !== null && (bool) $user->is_active) {
            $code = $this->generateCode();
            $expiresAt = now()->addMinutes(self::TTL_MINUTES);

            OtpChallenge::query()->create([
                'user_id' => $user->id,
                'type' => OtpChallenge::TYPE_EMAIL_OTP,
                'code_hash' => Hash::make($code),
                'context' => ['action' => 'login_otp'],
                'expires_at' => $expiresAt,
            ]);

            // Envía el código por email. Notifiable trait en User.
            $user->notify(new EmailOtpLoginNotification(
                code: $code,
                expiresInMinutes: self::TTL_MINUTES,
            ));
        }

        return response()->json(data: [
            'message' => 'Si el email está registrado, te enviamos un código de 6 dígitos.',
            'expires_in_minutes' => self::TTL_MINUTES,
        ]);
    }

    public function verify(EmailOtpVerifyRequest $request): JsonResponse
    {
        $email = strtolower((string) $request->input('email'));
        $code = (string) $request->input('code');

        $key = 'email-otp-verify:'.sha1($email);
        if (RateLimiter::tooManyAttempts(key: $key, maxAttempts: 5)) {
            return response()->json(
                data: ['message' => 'Demasiados intentos. Esperá unos minutos.'],
                status: 429,
            );
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            RateLimiter::hit(key: $key, decaySeconds: 60);

            return response()->json(
                data: ['message' => 'Código inválido o expirado.'],
                status: 422,
            );
        }

        // Busca el challenge más reciente no consumido del user para login.
        $challenge = OtpChallenge::query()
            ->where(column: 'user_id', operator: '=', value: $user->id)
            ->where(column: 'type', operator: '=', value: OtpChallenge::TYPE_EMAIL_OTP)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($challenge === null) {
            RateLimiter::hit(key: $key, decaySeconds: 60);

            return response()->json(
                data: ['message' => 'Código inválido o expirado.'],
                status: 422,
            );
        }

        if (! $challenge->isUsable()) {
            $challenge->update(['attempts' => $challenge->attempts + 1]);

            return response()->json(
                data: ['message' => 'Código inválido o expirado.'],
                status: 422,
            );
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->update(['attempts' => $challenge->attempts + 1]);
            RateLimiter::hit(key: $key, decaySeconds: 60);

            // Si alcanzó el máximo, invalidamos el challenge explícitamente.
            if ($challenge->fresh()->isExhausted()) {
                $challenge->update(['consumed_at' => now()]);
            }

            return response()->json(
                data: ['message' => 'Código inválido o expirado.'],
                status: 422,
            );
        }

        // OK: marca consumido, limpia rate limit, emite Sanctum token.
        $challenge->update(['consumed_at' => now()]);
        RateLimiter::clear(key: $key);

        if (! (bool) $user->is_active) {
            return response()->json(
                data: ['message' => 'Cuenta desactivada.'],
                status: 403,
            );
        }

        $token = $user->createToken(name: 'email-otp-login');

        return response()->json(data: [
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * Genera un código numérico de 6 dígitos (zero-padded).
     */
    private function generateCode(): string
    {
        $max = (10 ** self::CODE_LENGTH) - 1;

        return str_pad(
            (string) random_int(min: 0, max: $max),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }
}
