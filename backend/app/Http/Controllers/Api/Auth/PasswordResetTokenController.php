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
     * Body:
     *   - email (required)
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

        // Buscar el user en la tienda actual. Si no existe o no tiene
        // acceso a la tienda, devolvemos el mensaje genérico sin
        // generar token (no se filtra información).
        /** @var User|null $user */
        $user = User::query()
            ->where(column: 'email', operator: '=', value: $email)
            ->first();

        $shouldSend = $user !== null
            && $user->is_active
            && ($currentStoreId === null || $user->hasAccessToStore(storeId: $currentStoreId));

        $response = [
            'message' => 'Si el email existe, te enviamos un código.',
            'expires_minutes' => $ttlMinutes,
        ];

        if (! $shouldSend) {
            // Logueamos internamente para debugging sin exponer al cliente.
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

        // Dev mode: incluir el código en la respuesta para que el dev
        // pueda testear sin SMTP. En prod esto queda excluido.
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
     * Si el código coincide con un token válido (no expirado, no
     * usado, del mismo email, dentro de la tienda), cambia la
     * contraseña, marca el token como usado y devuelve el mismo shape
     * que /auth/login para auto-login.
     *
     * Body:
     *   - email (required)
     *   - code (required, 6 dígitos)
     *   - password (required, min 12, may/min/núm, confirmed)
     *   - password_confirmation (required)
     *   - device_name (optional)
     *
     * Response 200: { token, user }
     *
     * Errores 422:
     *   - email o code faltantes / formato inválido (form request).
     *   - "El código es inválido o ha expirado." si el token no
     *     existe, expiró, ya fue usado o no matchea el código.
     */
    public function reset(
        ResetPasswordRequest $request,
        StoreContextService $storeContext,
    ): JsonResponse {
        $email = strtolower(trim($request->validated('email')));
        $code = $request->validated('code');
        $password = $request->validated('password');

        // Throttle por IP: max 5 attempts cada 15 minutos.
        $throttleKey = 'reset-password:' . $request->ip();

        if (RateLimiter::tooManyAttempts(key: $throttleKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn(key: $throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Demasiados intentos. Probá de nuevo en {$seconds} segundos."],
            ]);
        }

        RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

        // Buscar tokens válidos para este email.
        /** @var PasswordResetToken|null $token */
        $token = PasswordResetToken::query()
            ->byEmail($email)
            ->valid()
            ->orderByDesc('expires_at')
            ->first();

        // No hay token válido (no existe, expiró, ya fue usado).
        // Mensaje genérico para no filtrar cuál es el problema.
        if ($token === null) {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'code' => ['El código es inválido o ha expirado.'],
            ]);
        }

        // Verificar código con hash.
        if (! Hash::check(value: $code, hashedValue: $token->token_hash)) {
            RateLimiter::hit(key: $throttleKey, decaySeconds: 900);

            throw ValidationException::withMessages([
                'code' => ['El código es inválido o ha expirado.'],
            ]);
        }

        // Multi-tenant: el user debe seguir activo y tener acceso a la
        // tienda actual. Esto evita que un token generado en otra tienda
        // pueda usarse acá.
        /** @var User|null $user */
        $user = $token->user;

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

        // Aplicar cambio de password y marcar token usado, atómicamente.
        $deviceName = $request->validated('device_name') ?? 'web-admin';

        [$tokenRecord] = DB::transaction(function () use ($token, $user, $password): array {
            $user->update(['password' => $password]);

            $token->update(['used_at' => now()]);

            return [$token];
        });

        // Crear Sanctum token para auto-login.
        $sanctumToken = $user->createToken(name: $deviceName);

        return response()->json(data: [
            'token' => $sanctumToken->plainTextToken,
            'user' => new UserResource(
                resource: $user->load(['role', 'stores']),
            ),
        ]);
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