<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\Auth\PasswordResetToken;
use App\Models\Auth\User;
use App\Notifications\Auth\PasswordResetCodeNotification;
use App\Services\Auth\RecoveryCodeService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints de recuperación de contraseña.
 *
 * - POST /auth/forgot-password → genera un código OTP, lo hashea y lo
 *   guarda en `password_reset_tokens`. Envía el código por email al
 *   usuario. La respuesta es siempre 200 OK con un mensaje genérico
 *   (no revela si el email existe — seguridad).
 *
 * - POST /auth/reset-password → valida email + código (no expirado, no
 *   usado), cambia la contraseña del usuario, marca el token como usado
 *   y crea un token Sanctum (auto-login) devolviendo el mismo shape
 *   que POST /auth/login.
 *
 * Multi-tenancy: ambos endpoints validan que el usuario esté asociado
 * a la tienda activa via `user_stores`. Un usuario puede tener cuentas
 * en distintas tiendas con la misma email pero distintos passwords (en
 * la práctica, las passwords son globales por user, pero el reset
 * solo aplica a la tienda actual para evitar cross-tenant abuse).
 *
 * Rate limiting: `forgot-password` se throttle a 3 requests / 5 min
 * por email. `reset-password` se throttle a 5 requests / 15 min por IP.
 *
 * Dev mode: cuando APP_ENV !== 'production', el código OTP se incluye
 * en la respuesta JSON de `forgot-password` para que el dev pueda
 * testear el flujo sin SMTP. En prod NO se incluye (solo va por email).
 *
 * preferred_recovery_method: la columna `users.preferred_recovery_method`
 * define qué flow usa el user para recuperar la cuenta. El controller
 * dispatcha según ese valor:
 *  - 'email' (default) → flow OTP estándar (este controller).
 *  - '2fa' → 422 en forgot-password (el user debe usar el flow 2FA
 *    en login: POST /login + /auth/2fa/verify-totp-login, y cambiar
 *    la contraseña desde su perfil autenticado).
 *  - 'recovery_codes' → 422 en forgot-password (el user debe usar
 *    reset-password con `method=recovery_codes` y uno de sus códigos
 *    de respaldo — RecoveryCodeService::verifyAndConsume).
 */
class PasswordResetTokenController extends Controller
{
    /**
     * Minutos de expiración del código OTP. Configurable via env.
     */
    private const DEFAULT_TTL_MINUTES = 15;

    /**
     * Largo del código OTP en dígitos.
     */
    private const CODE_LENGTH = 6;

    /**
     * Solicita un código de recuperación para el email dado.
     *
     * Siempre devuelve 200 OK con un mensaje genérico. Si el email
     * existe en la tienda activa y la cuenta está activa, genera
     * un código, lo guarda hasheado y envía el email. Si no existe
     * o la cuenta está inactiva, NO se genera nada — la respuesta
     * es idéntica para no filtrar información.
     *
     * Si el user tiene `preferred_recovery_method = '2fa'` o
     * 'recovery_codes', devuelve 422 con un mensaje indicando qué
     * flow alternativo usar (no se envía OTP en estos casos).
     *
     * Body:
     *   - email (required)
     *   - method (optional, default 'email'): 'email' | '2fa' | 'recovery_codes'.
     *     Default 'email'. Si el user existe y su preferred_recovery_method
     *     no coincide con method, devuelve 422.
     *
     * Response 200:
     *   - message: "Si el email existe, te enviamos un código."
     *   - code: string|null — solo en dev (APP_ENV !== 'production')
     *   - expires_minutes: int — TTL del código
     */
    public function forgot(
        ForgotPasswordRequest $request,
        StoreContextService $storeContext,
    ): JsonResponse {
        $email = strtolower(trim($request->validated('email')));
        $method = $request->validated('method') ?? 'email';

        // Throttle por email: max 3 attempts cada 5 minutos.
        $throttleKey = 'forgot-password:' . $email;

        if (RateLimiter::tooManyAttempts(key: $throttleKey, maxAttempts: 3)) {
            $seconds = RateLimiter::availableIn(key: $throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Demasiados intentos. Probá de nuevo en {$seconds} segundos."],
            ]);
        }

        RateLimiter::hit(key: $throttleKey, decaySeconds: 300);

        $currentStoreId = $storeContext->getStoreId();
        $ttlMinutes = (int) env(key: 'PASSWORD_RESET_TTL_MINUTES', default: self::DEFAULT_TTL_MINUTES);

        /** @var User|null $user */
        $user = User::query()
            ->where(column: 'email', operator: '=', value: $email)
            ->first();

        $shouldSend = $user !== null
            && $user->is_active
            && ($currentStoreId === null || $user->hasAccessToStore(storeId: $currentStoreId));

        // Dispatch según preferred_recovery_method. Solo válido 'email'
        // en este endpoint; los otros métodos devuelven 422 con un
        // mensaje explicando qué flow alternativo usar.
        if ($shouldSend && $user->preferred_recovery_method === '2fa') {
            throw ValidationException::withMessages([
                'method' => ['Tu cuenta usa autenticación de dos factores (2FA). Iniciá sesión con tu authenticator app y cambiá la contraseña desde tu perfil.'],
            ]);
        }

        if ($shouldSend && $user->preferred_recovery_method === 'recovery_codes') {
            throw ValidationException::withMessages([
                'method' => ['Tu cuenta usa recovery codes como método de recuperación. Usá POST /auth/reset-password con method=recovery_codes y uno de tus códigos de respaldo.'],
            ]);
        }

        $response = [
            'message' => 'Si el email existe, te enviamos un código.',
            'expires_minutes' => $ttlMinutes,
        ];

        if (! $shouldSend) {
            Log::info(
                message: 'forgot-password: email no asociado a tienda activa o cuenta inactiva',
                context: ['email' => $email, 'store_id' => $currentStoreId],
            );

            return response()->json(data: $response);
        }

        // Generar código y persistir hasheado.
        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(minutes: $ttlMinutes);

        DB::transaction(function () use ($user, $email, $code, $expiresAt, $request): void {
            // Invalidar tokens previos no usados para este email (anti-replay).
            PasswordResetToken::query()
                ->byEmail($email)
                ->valid()
                ->update(['used_at' => now()]);

            PasswordResetToken::query()->create([
                'user_id' => $user->id,
                'email' => $email,
                'token_hash' => Hash::make(value: $code),
                'expires_at' => $expiresAt,
                'request_ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', offset: 0, length: 255),
            ]);
        });

        // Enviar notificación. ShouldQueue → no bloquea la request.
        Notification::send(
            $user,
            new PasswordResetCodeNotification(
                code: $code,
                appName: config('app.name'),
                expiresMinutes: $ttlMinutes,
                supportEmail: $user->email,
            ),
        );

        if (! app()->environment('production')) {
            Log::info(
                message: 'forgot-password: código OTP generado',
                context: ['email' => $email, 'code' => $code, 'expires_at' => $expiresAt],
            );

            $response['code'] = $code;
            $response['dev'] = true;
        }

        return response()->json(data: $response);
    }

    /**
     * Restablece la contraseña del usuario validando email + código.
     *
     * Soporta dos métodos de recovery:
     *  - method=email (default): code es un OTP de 6 dígitos hasheado en
     *    `password_reset_tokens` (enviado por email en forgot-password).
     *  - method=recovery_codes: code es un recovery code alfanumérico
     *    de 10 chars, validado contra `recovery_codes` vía
     *    RecoveryCodeService::verifyAndConsume (que también lo marca
     *    como usado).
     *
     * Para method=recovery_codes, el user DEBE tener
     * `preferred_recovery_method = 'recovery_codes'`. Si no coincide,
     * devuelve 422.
     *
     * Si el código es válido, cambia la contraseña del user, marca el
     * token/recovery code como usado y emite un Sanctum token
     * (auto-login), devolviendo el mismo shape que /auth/login.
     *
     * Body:
     *   - email (required)
     *   - method (optional, default 'email'): 'email' | 'recovery_codes'
     *   - code (required): 6 dígitos si method=email, 10 chars alfanuméricos
     *     si method=recovery_codes
     *   - password (required, min 12, may/min/núm, confirmed)
     *   - password_confirmation (required)
     *   - device_name (optional)
     *
     * Response 200: { token, user }
     *
     * Errores 422:
     *   - email o code faltantes / formato inválido (form request).
     *   - method inválido (form request).
     *   - "El código es inválido o ha expirado." (method=email).
     *   - "El recovery code es inválido o ya fue usado." (method=recovery_codes).
     *   - "Tu cuenta no usa recovery_codes como método de recuperación."
     *     (method=recovery_codes pero preferred_recovery_method != 'recovery_codes').
     */
    public function reset(
        ResetPasswordRequest $request,
        StoreContextService $storeContext,
    ): JsonResponse {
        $email = strtolower(trim($request->validated('email')));
        $code = $request->validated('code');
        $password = $request->validated('password');
        $method = $request->validated('method') ?? 'email';

        // Throttle por IP: max 5 attempts cada 15 minutos.
        $throttleKey = 'reset-password:' . $request->ip();

        if (RateLimiter::tooManyAttempts(key: $throttleKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn(key: $throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Demasiados intentos. Probá de nuevo en {$seconds} segundos."],
            ]);
        }

        RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

        /** @var User|null $user */
        $user = User::query()
            ->where(column: 'email', operator: '=', value: $email)
            ->first();

        $currentStoreId = $storeContext->getStoreId();

        $canReset = $user !== null
            && $user->is_active
            && ($currentStoreId === null || $user->hasAccessToStore(storeId: $currentStoreId));

        if (! $canReset) {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'email' => ['No podés restablecer la contraseña en esta tienda.'],
            ]);
        }

        $deviceName = $request->validated('device_name') ?? 'web-admin';

        // Dispatch según método.
        if ($method === 'recovery_codes') {
            $this->resetWithRecoveryCode($user, $code, $password, $deviceName, $throttleKey);

            $sanctumToken = $user->createToken(name: $deviceName)->plainTextToken;

            return response()->json(data: [
                'token' => $sanctumToken,
                'user' => new UserResource(
                    resource: $user->load(['role', 'stores']),
                ),
            ]);
        }

        // Default: email OTP flow.
        $sanctumToken = $this->resetWithEmailOtp(
            $user,
            $email,
            $code,
            $password,
            $deviceName,
            $throttleKey,
            $currentStoreId,
        );

        return response()->json(data: [
            'token' => $sanctumToken,
            'user' => new UserResource(
                resource: $user->load(['role', 'stores']),
            ),
        ]);
    }

    /**
     * Resetea password usando un recovery code de un solo uso.
     *
     * Valida que el user efectivamente use recovery_codes como método
     * preferido. Verifica el code contra `recovery_codes` vía el
     * service (que también lo marca como usado) y cambia la password.
     */
    private function resetWithRecoveryCode(
        User $user,
        string $code,
        string $password,
        string $deviceName,
        string $throttleKey,
    ): void {
        if ($user->preferred_recovery_method !== 'recovery_codes') {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'method' => ['Tu cuenta no usa recovery_codes como método de recuperación.'],
            ]);
        }

        $service = app(abstract: RecoveryCodeService::class);

        if (! $service->verifyAndConsume($user, $code)) {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'code' => ['El recovery code es inválido o ya fue usado.'],
            ]);
        }

        DB::transaction(function () use ($user, $password): void {
            $user->update(attributes: ['password' => $password]);
        });
    }

    /**
     * Resetea password usando el OTP de email (flujo legacy).
     * Devuelve el Sanctum token creado para auto-login.
     */
    private function resetWithEmailOtp(
        User $user,
        string $email,
        string $code,
        string $password,
        string $deviceName,
        string $throttleKey,
        ?int $currentStoreId,
    ): string {
        /** @var PasswordResetToken|null $token */
        $token = PasswordResetToken::query()
            ->byEmail($email)
            ->valid()
            ->orderByDesc('expires_at')
            ->first();

        if ($token === null) {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'code' => ['El código es inválido o ha expirado.'],
            ]);
        }

        if (! Hash::check(value: $code, hashedValue: $token->token_hash)) {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'code' => ['El código es inválido o ha expirado.'],
            ]);
        }

        DB::transaction(function () use ($token, $user, $password): void {
            $user->update(attributes: ['password' => $password]);
            $token->update(attributes: ['used_at' => now()]);
        });

        return $user->createToken(name: $deviceName)->plainTextToken;
    }

    /**
     * Genera un código OTP numérico de CODE_LENGTH dígitos.
     *
     * Usa random_int para evitar sesgos de rand() y asegurar
     * distribución uniforme.
     */
    private function generateCode(): string
    {
        $min = (int) (10 ** (self::CODE_LENGTH - 1));
        $max = (int) ((10 ** self::CODE_LENGTH) - 1);

        return str_pad(
            string: (string) random_int(min: $min, max: $max),
            length: self::CODE_LENGTH,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );
    }
}
