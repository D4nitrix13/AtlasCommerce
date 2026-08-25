<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\OtpChallenge;
use App\Models\Auth\User;
use App\Services\Auth\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints de 2FA / TOTP.
 *
 * Flujo:
 *   1. POST /auth/2fa/totp/setup — genera secret + QR, NO activa 2FA
 *      todavía. El user escanea el QR con su authenticator app.
 *   2. POST /auth/2fa/totp/verify { code } — verifica el code y, si
 *      es correcto, activa 2FA (enabled_at = now). El challenge se
 *      registra en otp_challenges para audit.
 *   3. DELETE /auth/2fa/totp — desactiva 2FA y borra recovery codes
 *      y challenges activos del user. **Requiere re-auth**: si 2FA
 *      está activa → `current_totp_code`; si NO está activa → `password`.
 *
 * El secret se encrypta con Laravel Crypt antes de persistir
 * (cast 'encrypted' en el model).
 */
class TotpController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
    ) {}

    /**
     * Genera un secret + QR para que el user escanee con su
     * authenticator app. NO activa 2FA todavía (eso es verify).
     *
     * Requiere que el user esté autenticado (Sanctum).
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totp->generateSecret();
        $uri = $this->totp->provisioningUri($user, $secret);

        // Persistimos el secret encryptado sin enabled_at (no activo
        // todavía). Si el user cancela el setup, queda como draft y
        // puede reintentar con la misma sesión de QR regenerando.
        $user->twoFactorSetting()->updateOrCreate(
            attributes: ['user_id' => $user->id],
            values: [
                'secret' => $secret,
                'enabled_at' => null,
            ],
        );

        return response()->json(data: [
            'secret' => $secret,
            'otpauth_url' => $uri,
            'digits' => TotpService::DIGITS,
            'period' => TotpService::STEP_SECONDS,
            'algorithm' => 'SHA1',
        ], status: 200);
    }

    /**
     * Verifica el code de 6 dígitos y, si es correcto, activa 2FA.
     * También genera el primer set de recovery codes automáticamente.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'code' => ['required', 'string', 'size:6'],
        ]);

        // Throttle IP-based (10/5min): defense-in-depth contra brute force
        // del código TOTP. Aunque TotpService valida ventana ±1 step,
        // un atacante con acceso a la sesión podría iterar códigos
        // random sin disparar el per-challenge MAX_ATTEMPTS.
        $verifyKey = 'totp-verify:'.$request->ip();
        if (RateLimiter::tooManyAttempts(key: $verifyKey, maxAttempts: 10)) {
            throw ValidationException::withMessages([
                'code' => ['Demasiados intentos. Probá de nuevo en '.RateLimiter::availableIn(key: $verifyKey).' segundos.'],
            ]);
        }
        RateLimiter::hit(key: $verifyKey, decaySeconds: 300);

        $user = $request->user();
        $code = $request->input(key: 'code');

        $setting = $user->twoFactorSetting;
        if ($setting === null) {
            return response()->json(data: ['message' => 'Primero ejecuta setup.'], status: 400);
        }

        if (! $this->totp->verify($user, $code)) {
            return response()->json(data: ['message' => 'Código inválido.'], status: 422);
        }

        // Activa 2FA
        $setting->enabled_at = now();
        $setting->save();

        // Genera recovery codes iniciales (10 por default)
        $recoveryCodesService = app(\App\Services\Auth\RecoveryCodeService::class);
        $recoveryCodes = $recoveryCodesService->generateFor($user);
        $setting->recovery_codes_generated_at = now();
        $setting->save();

        // Audit
        OtpChallenge::create([
            'user_id' => $user->id,
            'type' => OtpChallenge::TYPE_TOTP,
            'code_hash' => bcrypt(value: $code),
            'context' => ['action' => 'enable_2fa'],
            'expires_at' => now(),
            'consumed_at' => now(),
        ]);

        return response()->json(data: [
            'message' => '2FA habilitada.',
            'recovery_codes' => $recoveryCodes,
            'message_recovery' => 'Guarda estos códigos en lugar seguro. Son la única forma de recuperar tu cuenta si pierdes acceso al authenticator app.',
        ], status: 200);
    }

    /**
     * Desactiva 2FA. Borra recovery codes y challenges activos.
     *
     * Requiere proof-of-identity: si 2FA está activa, el user debe
     * confirmar con `current_totp_code` (no se acepta password). Si
     * 2FA NO está activa, el user debe confirmar con `password`. Esta
     * política evita que un atacante con sesión activa pero sin
     * acceso al authenticator pueda deshabilitar 2FA.
     *
     * En éxito persiste `OtpChallenge` audit row con
     * `context.action='disable_2fa'` y emite `Log::warning('2fa.disabled')`.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'password' => ['nullable', 'string'],
            'current_totp_code' => ['nullable', 'string', 'digits:6'],
        ]);

        $user = $request->user();
        $has2fa = $user->twoFactorSetting !== null;

        if ($has2fa) {
            abort_unless(
                $request->filled(key: 'current_totp_code'),
                422,
                'Con 2FA activo, debes confirmar con current_totp_code.',
            );
            $valid = app(abstract: TotpService::class)->verify(
                user: $user,
                code: $request->input(key: 'current_totp_code'),
            );
            abort_unless($valid, 422, 'Código TOTP inválido.');
            $method = 'totp_code';
        } else {
            abort_unless(
                $request->filled(key: 'password'),
                422,
                'Debes confirmar con password.',
            );
            abort_unless(
                Hash::check(value: $request->input(key: 'password'), hashedValue: $user->password),
                422,
                'Password incorrecto.',
            );
            $method = 'password';
        }

        Log::warning(message: '2fa.disabled', context: [
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'method' => $method,
        ]);

        DB::transaction(callback: function () use ($user, $method): void {
            $user->twoFactorSetting()?->delete();
            $user->recoveryCodes()->delete();
            $user->otpChallenges()->delete();

            OtpChallenge::query()->create(attributes: [
                'user_id' => $user->id,
                'type' => OtpChallenge::TYPE_TOTP,
                // bin2hex(random_bytes(...)) garantiza string sin null bytes
                // (PHP 8+ rechaza null chars en password_hash).
                'code_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'context' => ['action' => 'disable_2fa', 'method' => $method],
                'expires_at' => now(),
                'consumed_at' => now(),
            ]);
        });

        return response()->json(data: ['message' => '2FA deshabilitada.'], status: 200);
    }

    /**
     * Estado actual de 2FA del user autenticado.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $setting = $user->twoFactorSetting;
        $codes = $user->unusedRecoveryCodesCount();

        return response()->json(data: [
            'enabled' => $user->hasTwoFactorEnabled(),
            'enabled_at' => $setting?->enabled_at,
            'unused_recovery_codes' => $codes,
        ], status: 200);
    }
}
