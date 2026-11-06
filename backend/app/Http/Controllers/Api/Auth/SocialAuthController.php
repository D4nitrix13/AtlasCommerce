<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\SocialAuth\OAuthAdminSignupDisabledException;
use App\Exceptions\SocialAuth\OAuthEmailConflictException;
use App\Exceptions\SocialAuth\OAuthEmailUnverifiedException;
use App\Exceptions\SocialAuth\OAuthException;
use App\Exceptions\SocialAuth\OAuthProviderNotConfiguredException;
use App\Exceptions\SocialAuth\OAuthStateMismatchException;
use App\Exceptions\SocialAuth\OAuthStateMissingException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\OAuthErrorResource;
use App\Models\Auth\User;
use App\Services\Auth\SocialAccountService;
use App\Support\Auth\OAuthRedirectTarget;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * OAuth social login (Google + Facebook).
 *
 *   GET /api/auth/social/{provider}/redirect
 *     → 302 al provider authorize URL (state en session — Socialite nativo)
 *
 *   GET /api/auth/social/{provider}/callback
 *     → resuelve identidad via SocialAccountService, valida tenant
 *       access + TOTP, emite Sanctum token + stateful cookie;
 *       failures → 302 al login page con `?flash={code}` (PR 2b)
 *
 * Multi-tenancy: el middleware `store.domain` resuelve el tenant
 * activo (ver `routes/api.php` line 65-68). El callback URL se
 * construye per-host (`$request->getSchemeAndHttpHost()`) en redirect
 * para mantener el cookie domain aislado por tenant.
 *
 * State CSRF (R2):
 *   - Socialite 5.x maneja state vía `AbstractProvider::hasInvalidState()`
 *     (compare con `hash_equals`).
 *   - Defense-in-depth: el redirect endpoint exige `$request->hasSession()`
 *     para que Socialite tenga dónde guardar el state.
 *
 * Failure UX:
 *   - En redirect, guardamos `oauth_redirect_target` en session con la
 *     ruta del login page del frontend (reviewer #3: NO Referer, porque
 *     el callback llega con Referer del provider domain).
 *   - En callback, leemos la session target y armamos el redirect con
 *     `?flash={code}` para que el OAuthFlashToast (PR 4) muestre el i18n.
 */
class SocialAuthController extends Controller
{
    /**
     * Providers aprobados para OAuth.
     *
     * @var list<string>
     */
    private const SUPPORTED_PROVIDERS = ['google', 'facebook'];

    /**
     * Session key para persistir el redirect target entre el redirect
     * endpoint y el callback. Se pop en el callback (one-shot).
     */
    private const SESSION_REDIRECT_TARGET = 'oauth_redirect_target';

    public function __construct(
        private readonly SocialAccountService $socialAccountService,
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Inicia el flujo OAuth: 302 al provider authorize URL.
     *
     * Además de los guards de provider + session + config, persistimos
     * el login URL en session para que el callback pueda armar el
     * `?flash=` redirect sin depender del Referer (reviewer #3).
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
            if (! $request->hasSession()) {
                Log::warning(message: 'oauth_redirect_without_session', context: [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                ]);
                throw new OAuthStateMissingException(provider: $provider);
            }

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

            // Session-stored target (reviewer #3): guardamos el path
            // del login page para que el callback pueda armar el
            // redirect sin depender del Referer (cross-origin).
            // El frontend hace `window.location.assign(...)` desde el
            // login page → el Referer del redirect endpoint SÍ es la
            // login page (no el provider domain — eso pasa recién en
            // el callback). Capturamos ese path acá.
            $refererPath = parse_url(
                url: (string) $request->headers->get('referer', '/'),
                component: PHP_URL_PATH,
            ) ?: '/';
            $request->session()->put(key: self::SESSION_REDIRECT_TARGET, value: $refererPath);

            $callbackUrl = $request->getSchemeAndHttpHost().'/api/v1/auth/social/'.$provider.'/callback';

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
     * Flow:
     *   1. provider guard (400)
     *   2. `Socialite::driver($provider)->user()` (puede tirar InvalidStateException
     *      → 400 oauth_state_mismatch + Log::warning)
     *   3. `SocialAccountService::findOrCreateFromProvider()` — throws
     *      OAuthEmailUnverifiedException / OAuthEmailConflictException /
     *      OAuthAdminSignupDisabledException
     *   4. `hasAccessToStore($currentStoreId)` (422 si no)
     *   5. TOTP routing: si el user tiene 2FA, devolver `2fa_required`
     *      envelope (mismo formato que `AuthController::login`)
     *   6. Happy path: `auth('web')->login($user)` + `createToken('oauth-'.$provider)`
     *      + `update(['last_login_at' => now()])` + JSON response
     *   7. Cualquier `OAuth*Exception` → 302 al login page con `?flash={code}`
     */
    public function callback(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        if (! in_array(needle: $provider, haystack: self::SUPPORTED_PROVIDERS, strict: true)) {
            return Response::json(
                data: ['message' => "Provider '{$provider}' no soportado."],
                status: 400,
            );
        }

        try {
            // State CSRF — Socialite 5.x nativo.
            try {
                $providerUser = Socialite::driver($provider)->user();
            } catch (InvalidStateException) {
                Log::warning(message: 'oauth_state_mismatch', context: [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                ]);
                throw new OAuthStateMismatchException(provider: $provider);
            }

            // Resolve identity (5 branches, A3-A8).
            $user = $this->socialAccountService->findOrCreateFromProvider(
                providerUser: $providerUser,
                host: (string) $request->getHost(),
                provider: $provider,
            );

            // Tenant gate (A9) — same logic as AuthController::login.
            $currentStoreId = $this->storeContext->getStoreId();
            if ($currentStoreId !== null && ! $user->hasAccessToStore(storeId: $currentStoreId)) {
                Log::warning(message: 'oauth_tenant_gate_denied', context: [
                    'provider' => $provider,
                    'user_id' => $user->id,
                    'store_id' => $currentStoreId,
                ]);

                return Response::json(
                    data: ['message' => 'No tienes acceso a esta tienda.'],
                    status: 422,
                );
            }

            // TOTP routing (A11) — mirror AuthController::login.
            if ($user->hasTwoFactorEnabled()) {
                return $this->build2faRequiredResponse(user: $user);
            }

            // Happy path.
            $deviceName = 'oauth-'.$provider;
            if ($request->hasSession()) {
                Auth::guard(name: 'web')->login($user);
            }
            $token = $user->createToken(name: $deviceName);
            $user->update(attributes: ['last_login_at' => now()]);

            return Response::json(data: [
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ],
            ], status: 200);
        } catch (OAuthException $e) {
            // Failure flash redirect — uses session-stored target (reviewer #3).
            return $this->flashRedirect($request, $e);
        }
    }

    /**
     * Renderiza una `OAuthException` como flash redirect.
     *
     * El target se lee de session (one-shot via `pull()`). Si no hay
     * session target, caemos al login URL derivado del host.
     */
    private function flashRedirect(Request $request, OAuthException $e): RedirectResponse
    {
        $storedTarget = null;
        if ($request->hasSession()) {
            $storedTarget = $request->session()->pull(key: self::SESSION_REDIRECT_TARGET, default: null);
        }

        $loginPath = OAuthRedirectTarget::fromRequest(
            originalPath: $storedTarget ?? '/',
            host: (string) $request->getHost(),
        );

        $url = $loginPath.'?flash='.urlencode(string: $e->oauthCode());

        Log::info(message: 'oauth_failure_flash', context: [
            'code' => $e->oauthCode(),
            'target' => $loginPath,
        ]);

        return redirect()->to($url, status: 302);
    }

    /**
     * Renderiza una `OAuthException` como JSON 400/503.
     *
     * Helper estático reutilizado por `redirect()` (no usa session
     * target — redirect nunca falla con `?flash=`).
     */
    public static function oauthError(Throwable $e): JsonResponse
    {
        $statusCode = match (true) {
            $e instanceof OAuthProviderNotConfiguredException => 503,
            default => 400,
        };

        $payload = OAuthErrorResource::from($e)->toArray(request: Request::create(uri: '/'));

        return Response::json(data: $payload, status: $statusCode);
    }

    /**
     * Construye el envelope `2fa_required` (A11), idéntico a
     * `AuthController::login` lines 94-112.
     */
    private function build2faRequiredResponse(User $user): JsonResponse
    {
        $challenge = \App\Models\Auth\OtpChallenge::create(attributes: [
            'user_id' => $user->id,
            'type' => \App\Models\Auth\OtpChallenge::TYPE_TOTP,
            'code_hash' => password_hash(
                password: bin2hex(random_bytes(length: 16)),
                algo: PASSWORD_BCRYPT,
            ),
            'context' => ['action' => 'oauth_login_2fa', 'device_name' => 'oauth'],
            'expires_at' => now()->addMinutes(value: 5),
        ]);

        return Response::json(data: [
            'status' => '2fa_required',
            'challenge_token' => $challenge->id,
            'method' => 'totp',
            'expires_at' => $challenge->expires_at,
        ], status: 200);
    }
}
