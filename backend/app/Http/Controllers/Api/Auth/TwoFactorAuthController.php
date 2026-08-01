<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\TwoFactorMethod;
use App\Models\Auth\TwoFactorSetting;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de autenticación de dos factores.
 *
 * Gestiona el ciclo de vida completo de 2FA para usuarios autenticados:
 *
 * - **Estado** (`show`): retorna si 2FA está habilitado, el método preferido
 *   y la lista de métodos registrados (sin exponer datos sensibles).
 * - **Envío** (`sendEmailOtp`): genera un código OTP y lo envía por correo.
 * - **Verificación** (`verifyEmailOtp`): valida el código y activa 2FA si es correcto.
 * - **Desactivación** (`disable`): desactiva 2FA y todos los métodos registrados.
 *
 * La lógica de generación y verificación de códigos vive en `EmailOtpService`.
 * Este controlador solo orquesta las operaciones y gestiona las respuestas HTTP.
 *
 * Todos los endpoints requieren un token Sanctum válido (middleware auth:sanctum).
 */
class TwoFactorAuthController extends Controller
{
    /**
     * Muestra el estado actual de 2FA del usuario.
     *
     * Endpoint: `GET /api/auth/2fa`
     *
     * Retorna:
     * - `enabled`: si 2FA está activo.
     * - `preferred_method`: método preferido (email_otp, totp, passkey) o null.
     * - `required`: si 2FA es obligatorio (preparado para uso futuro).
     * - `enabled_at`: fecha de activación.
     * - `methods`: lista de métodos registrados (sin `encrypted_secret` ni otros datos sensibles).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $setting = $user->twoFactorSetting()->first();

        // Se seleccionan solo campos seguros para exponer en la API.
        $methods = $user->twoFactorMethods()
            ->select([
                'id',
                'method',
                'display_name',
                'destination',
                'active',
                'verified',
                'verified_at',
                'created_at',
            ])
            ->orderBy('method')
            ->get();

        return response()->json(data: [
            'enabled' => (bool) $setting?->enabled,
            'preferred_method' => $setting?->preferred_method,
            'required' => (bool) $setting?->required,
            'enabled_at' => $setting?->enabled_at,
            'methods' => $methods,
        ], status: 200);
    }

    /**
     * Envía un código OTP al correo del usuario.
     *
     * Endpoint: `POST /api/auth/2fa/email/send`
     *
     * Delega a `EmailOtpService::send()` que:
     * 1. Genera el código numérico.
     * 2. Lo guarda como hash en `otp_challenges`.
     * 3. Lo envía por correo.
     *
     * Se registra metadata de seguridad (IP, user-agent) en el challenge
     * para auditoría y detección de actividad sospechosa.
     */
    public function sendEmailOtp(Request $request, EmailOtpService $emailOtpService): JsonResponse
    {
        $user = $request->user();

        $challenge = $emailOtpService->send(
            user: $user,
            purpose: 'enable_2fa',
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(data: [
            'message' => 'Security code sent.',
            'expires_at' => $challenge->expires_at,
            'destination' => $challenge->destination,
        ], status: 200);
    }

    /**
     * Verifica el código OTP y activa 2FA si es correcto.
     *
     * Endpoint: `POST /api/auth/2fa/email/verify`
     *
     * Flujo de activación:
     * 1. Valida que el código tenga entre 4 y 10 caracteres.
     * 2. Delega la verificación a `EmailOtpService::verify()`.
     * 3. Si el código es correcto:
     *    a. Crea/actualiza `two_factor_settings` con `enabled: true`.
     *    b. Crea/actualiza `two_factor_methods` con el método `email_otp` verificado.
     * 4. Si el código es incorrecto o expirado, retorna error 422.
     *
     * Se usa `updateOrCreate` para ser idempotente: si el usuario repite
     * el proceso, se actualiza en lugar de duplicar registros.
     */
    public function verifyEmailOtp(Request $request, EmailOtpService $emailOtpService): JsonResponse
    {
        $validated = $request->validate(rules: [
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $user = $request->user();

        $verified = $emailOtpService->verify(
            user: $user,
            code: $validated['code'],
            purpose: 'enable_2fa',
        );

        if (! $verified) {
            return response()->json(data: [
                'message' => 'Invalid or expired security code.',
            ], status: 422);
        }

        // Se activa 2FA global del usuario después de verificación exitosa.
        // updateOrCreate garantiza idempotencia en caso de múltiples llamadas.
        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'enabled' => true,
                'preferred_method' => 'email_otp',
                'enabled_at' => now(),
            ],
        );

        // Se registra el método email_otp como verificado y activo.
        // El destination se guarda para referencia futura (ej. listar métodos).
        TwoFactorMethod::updateOrCreate(
            [
                'user_id' => $user->id,
                'method' => 'email_otp',
            ],
            [
                'display_name' => 'Main email',
                'destination' => $user->email,
                'active' => true,
                'verified' => true,
                'verified_at' => now(),
            ],
        );

        return response()->json(data: [
            'message' => 'Email OTP enabled successfully.',
        ], status: 200);
    }

    /**
     * Desactiva 2FA para el usuario.
     *
     * Endpoint: `POST /api/auth/2fa/disable`
     *
     * Flujo de desactivación:
     * 1. Verifica que 2FA esté habilitado (si no, retorna 422).
     * 2. Actualiza `two_factor_settings`:
     *    - `enabled: false`
     *    - `preferred_method: null`
     *    - `enabled_at: null`
     * 3. Desactiva TODOS los métodos registrados (`active: false`).
     *
     * Nota: no se eliminan los registros, solo se marcan como inactivos.
     * Esto preserva el historial para auditoría.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $setting = $user->twoFactorSetting()->first();

        if (! $setting || ! $setting->enabled) {
            return response()->json(data: [
                'message' => 'Two-factor authentication is not enabled.',
            ], status: 422);
        }

        $user->twoFactorSetting()->update([
            'enabled' => false,
            'preferred_method' => null,
            'enabled_at' => null,
        ]);

        $user->twoFactorMethods()->update([
            'active' => false,
        ]);

        return response()->json(data: [
            'message' => 'Two-factor authentication disabled successfully.',
        ], status: 200);
    }
}
