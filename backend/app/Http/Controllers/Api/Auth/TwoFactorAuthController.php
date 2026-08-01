<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\TwoFactorMethod;
use App\Models\Auth\TwoFactorSetting;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar la autenticación de dos factores (2FA).
 *
 * Expone los endpoints para consultar el estado de 2FA, enviar códigos OTP
 * por correo, verificar códigos y desactivar 2FA.
 *
 * Flujo principal:
 * 1. `show` → consulta el estado actual de 2FA del usuario.
 * 2. `sendEmailOtp` → genera y envía un código OTP al correo del usuario.
 * 3. `verifyEmailOtp` → valida el código y activa 2FA si es correcto.
 * 4. `disable` → desactiva 2FA y deshabilita todos los métodos.
 */
class TwoFactorAuthController extends Controller
{
    /**
     * Muestra el estado actual de 2FA del usuario autenticado.
     *
     * Retorna si 2FA está habilitado, el método preferido, si es obligatorio,
     * la fecha de activación y la lista de métodos configurados.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $setting = $user->twoFactorSetting()->first();

        $methods = $user->twoFactorMethods()
            ->select([
                'id', 'method', 'display_name', 'destination',
                'active', 'verified', 'verified_at', 'created_at',
            ])
            ->orderBy('method')
            ->get();

        return response()->json([
            'enabled' => (bool) $setting?->enabled,
            'preferred_method' => $setting?->preferred_method,
            'required' => (bool) $setting?->required,
            'enabled_at' => $setting?->enabled_at,
            'methods' => $methods,
        ], status: 200);
    }

    /**
     * Envía un código OTP al correo del usuario para activar 2FA.
     *
     * Delega a `EmailOtpService::send()` que genera el código, lo almacena
     * hasheado en la BD y envía la notificación por correo.
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

        return response()->json([
            'message' => 'Security code sent.',
            'expires_at' => $challenge->expires_at,
            'destination' => $challenge->destination,
        ], status: 200);
    }

    /**
     * Verifica el código OTP y activa 2FA si es correcto.
     *
     * Valida que el código no esté expirado, que tenga intentos disponibles
     * y que coincida con el hash almacenado. Si es correcto, crea o actualiza
     * la configuración de 2FA y el método email_otp del usuario.
     */
    public function verifyEmailOtp(Request $request, EmailOtpService $emailOtpService): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $user = $request->user();

        $verified = $emailOtpService->verify(
            user: $user,
            code: $validated['code'],
            purpose: 'enable_2fa',
        );

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid or expired security code.',
            ], status: 422);
        }

        TwoFactorSetting::updateOrCreate(
            attributes: ['user_id' => $user->id],
            values: [
                'enabled' => true,
                'preferred_method' => 'email_otp',
                'enabled_at' => now(),
            ],
        );

        TwoFactorMethod::updateOrCreate(
            attributes: [
                'user_id' => $user->id,
                'method' => 'email_otp',
            ],
            values: [
                'display_name' => 'Main email',
                'destination' => $user->email,
                'active' => true,
                'verified' => true,
                'verified_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Email OTP enabled successfully.',
        ], status: 200);
    }

    /**
     * Desactiva la autenticación de dos factores del usuario.
     *
     * Deshabilita la configuración 2FA y todos los métodos asociados.
     * No elimina los métodos registrados, solo los marca como inactivos.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $setting = $user->twoFactorSetting()->first();

        if (! $setting || ! $setting->enabled) {
            return response()->json([
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

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.',
        ], status: 200);
    }
}
