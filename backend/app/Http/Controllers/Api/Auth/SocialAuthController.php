<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\SocialAuth\OAuthException;
use App\Exceptions\SocialAuth\OAuthProviderNotConfiguredException;
use App\Exceptions\SocialAuth\OAuthStateMissingException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\OAuthErrorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Laravel\Socialite\Facades\Socialite;

/**
 * OAuth social login (Google + Facebook).
 *
 *   GET /api/auth/social/{provider}/redirect
 *     → 302 a Google/Facebook con `state` random guardado en session
 *       (Socialite 5.x nativo, design §6)
 *   GET /api/auth/social/{provider}/callback
 *     → resuelve identidad, devuelve token Sanctum + cookie o 302
 *       con `?flash={code}` al login page en failure
 *
 * Multi-tenancy: el middleware `store.domain` resuelve el tenant
 * activo (ver `routes/api.php`). El callback URL se construye per-host
 * (`$request->getSchemeAndHttpHost()`) para mantener el cookie domain
 * aislado por tenant (`SetSessionDomainFromHost`).
 *
 * Defense-in-depth:
 *   - `$request->hasSession()` gate en `redirect()`: evita que
 *     Socialite escriba state en una sesión que no existe cuando
 *     llegue el callback (`OAuthStateMissingException` → 400).
 *   - `config(services.{p}.client_id)` + `client_secret` truthy
 *     check: fail-closed con 503 si el provider no está configurado
 *     en este entorno (`OAuthProviderNotConfiguredException`).
 *
 * PR 1 implementa solo `redirect()` completo. `callback()` queda como
 * 501 hasta PR 2 — el spec A3-A12 + R1/R9/R12 se cubren cuando el
 * `SocialAccountService::findOrCreateFromProvider` esté completo.
 */
class SocialAuthController extends Controller
{
    /**
     * Providers aprobados para OAuth en este proyecto.
     *
     * Mantener en sync con `config('services.{provider}')` blocks +
     * `tests/Feature/Auth/SocialAuthConfigurationTest.php`.
     *
     * @var list<string>
     */
    private const SUPPORTED_PROVIDERS = ['google', 'facebook'];

    /**
     * Inicia el flujo OAuth: 302 al provider authorize URL.
     *
     * Pasos:
     *   1. Provider ∈ SUPPORTED (400 si no)
     *   2. `$request->hasSession()` (400 oauth_state_required si no)
     *   3. `config(services.{p}.client_id)` + `client_secret` truthy
     *      (503 oauth_provider_not_configured si no)
     *   4. Construye callback URL per-host (`$request->getSchemeAndHttpHost()`)
     *   5. `Socialite::driver($provider)->redirectUrl($url)->redirect()`
     *      (Socialite escribe el `state` random en session vía
     *      `AbstractProvider::getState()`)
     */
    public function redirect(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        if (! in_array(needle: $provider, haystack: self::SUPPORTED_PROVIDERS, strict: true)) {
            return Response::json(
                data: ['message' => "Provider '{$provider}' no soportado."],
                status: 400,
            );
        }

        try {
            // Defense-in-depth: Socialite escribe el state en session
            // durante `redirect()`. Si la request llega sin session
            // cookie, ese state se va a perder antes del callback y
            // la validación va a fallar con un error confuso.
            if (! $request->hasSession()) {
                Log::warning(message: 'oauth_redirect_without_session', context: [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                ]);
                throw new OAuthStateMissingException(provider: $provider);
            }

            // Fail-closed: ambos client_id y client_secret deben estar
            // configurados. client_secret se usa en el callback (token
            // exchange) pero si lo dejamos pasar aquí, el usuario igual
            // va a fallar al volver — preferimos cortar temprano.
            $clientId = config(key: "services.{$provider}.client_id");
            $clientSecret = config(key: "services.{$provider}.client_secret");
            if ($clientId === null || $clientId === '' || $clientSecret === null || $clientSecret === '') {
                Log::warning(message: 'oauth_provider_not_configured', context: [
                    'provider' => $provider,
                    'has_client_id' => $clientId !== null && $clientId !== '',
                    'has_client_secret' => $clientSecret !== null && $clientSecret !== '',
                ]);
                throw new OAuthProviderNotConfiguredException(provider: $provider);
            }

            // Callback URL per-host: el host actual del tenant, NO la
            // base de env. Esto mantiene el cookie domain aislado por
            // tenant (SetSessionDomainFromHost).
            $callbackUrl = $request->getSchemeAndHttpHost() . '/api/auth/social/' . $provider . '/callback';

            return Socialite::driver($provider)
                ->redirectUrl($callbackUrl)
                ->redirect();
        } catch (OAuthException $e) {
            return self::oauthError($e);
        }
    }

    /**
     * Recibe el callback del provider con `code + state`.
     *
     * Implementación completa en PR 2 (tasks §2.2 task 2.5): resuelve
     * identidad via `SocialAccountService::findOrCreateFromProvider`,
     * aplica tenant gate + TOTP routing, emite Sanctum token + cookie,
     * o redirige con `?flash=` al login page en failure.
     *
     * En PR 1 devuelve 501 explícito para que el frontend storefront
     * (que ya renderiza los botones) reciba un error claro y el resto
     * de los tests pueda escribir contra el comportamiento actual.
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        if (! in_array(needle: $provider, haystack: self::SUPPORTED_PROVIDERS, strict: true)) {
            return Response::json(
                data: ['message' => "Provider '{$provider}' no soportado."],
                status: 400,
            );
        }

        Log::info(message: 'social_login.callback_received', context: ['provider' => $provider]);

        // PR 2: implementar findOrCreateFromProvider + tenant gate +
        // TOTP routing + token issuance. Por ahora fail-closed con 501
        // explícito para que el frontend storefront vea el error.
        return Response::json(
            data: [
                'message' => "Callback de {$provider} aún no implementado.",
                'hint' => 'Disponible en PR 2 del SDD oauth-google-facebook-login.',
            ],
            status: 501,
        );
    }

    /**
     * Render serializa una `OAuthException` al envelope uniforme.
     *
     * Helper público para que PR 2 lo reutilice en el `callback()`.
     * Hoy está expuesto pero sin uso real (callback devuelve 501);
     * lo dejo público para que el próximo PR no tenga que tocar
     * visibilidad.
     */
    public static function oauthError(\Throwable $e): JsonResponse
    {
        $statusCode = match (true) {
            $e instanceof OAuthProviderNotConfiguredException => 503,
            default => 400,
        };

        $payload = OAuthErrorResource::from($e)->toArray(request: \Illuminate\Http\Request::create(uri: '/'));

        return Response::json(data: $payload, status: $statusCode);
    }
}
