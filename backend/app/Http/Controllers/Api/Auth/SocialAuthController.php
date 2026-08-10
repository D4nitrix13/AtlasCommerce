<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stub para login social (Google + Facebook).
 *
 * Devuelve 501 Not Implemented con un mensaje claro. La intención es
 * que el frontend tenga dónde apuntar los botones sociales mientras
 * se decide si se instala laravel/socialite + se configuran las
 * credenciales OAuth de los providers.
 *
 * Implementación completa cuando se apruebe agregar Socialite:
 *   1. composer require laravel/socialite
 *   2. .env: GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET /
 *            FACEBOOK_CLIENT_ID / FACEBOOK_CLIENT_SECRET
 *   3. config/services.php: agregar block google + facebook
 *   4. Reemplazar los métodos de este controller por
 *      Socialite::driver($provider)->redirect() y el callback
 *      con ->user() → buscar/crear User → createToken().
 *   5. Definir rutas en routes/api.php:
 *        GET  /auth/social/{provider}/redirect
 *        GET  /auth/social/{provider}/callback
 *
 * Providers soportados en este stub: google, facebook.
 */
class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'facebook'];

    public function redirect(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, strict: true)) {
            return response()->json(data: [
                'message' => "Provider '{$provider}' no soportado.",
            ], status: 400);
        }

        Log::info(
            message: 'social_login.redirect_requested',
            context: ['provider' => $provider],
        );

        return response()->json(data: [
            'message' => "Login con {$provider} aún no está configurado.",
            'hint' => 'Falta composer require laravel/socialite y configurar las credenciales OAuth del provider en .env.',
        ], status: 501);
    }

    public function callback(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, strict: true)) {
            return response()->json(data: [
                'message' => "Provider '{$provider}' no soportado.",
            ], status: 400);
        }

        return response()->json(data: [
            'message' => "Callback de {$provider} aún no implementado.",
        ], status: 501);
    }
}