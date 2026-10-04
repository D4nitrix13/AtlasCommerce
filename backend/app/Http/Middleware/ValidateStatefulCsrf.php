<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CSRF para rutas API stateful (cookie auth, RF-SEC-018).
 *
 * Reemplaza a `VerifyCsrfToken` de Laravel en el sub-pipeline de Sanctum
 * (`config/sanctum.php middleware.validate_csrf_token`). La razón:
 *
 *   1. El `PreventRequestForgery` de Laravel, dentro de su sub-pipeline,
 *      hardcodea `CookieValuePrefix::remove($this->encrypter->decrypt(
 *      $header, static::serialized()))` para X-XSRF-TOKEN. Asume que
 *      TODOS los cookies XSRF tienen un prefijo HMAC de 40 chars que
 *      precede al valor. Cuando nuestro `EncryptCookies` cifra la
 *      cookie XSRF-TOKEN con el formato estándar Laravel (base64 de
 *      JSON con iv/value/mac/tag), el header llega al backend
 *      ENCRIPTADO. Laravel lo desencripta OK, pero luego `remove(41)`
 *      rompe el JSON de 40 chars quitando bytes válidos del medio.
 *
 *   2. Encima, `decrypt($header, false)` se llama con `$serialize=false`
 *      (default de `static::$serialize`). Para nuestro cookie JSON
 *      encrypted esto es correcto.
 *
 * Este middleware implementa el flujo Sanctum doble-submit cookie
 * correctamente: desencripta con el encrypter y luego strip el prefijo
 * de 41 chars (40 HMAC + 1 separador `|`) que Laravel agregó al
 * setear la cookie.
 *
 * Solo aplica validación si:
 *   - Método HTTP es state-mutating (POST/PUT/PATCH/DELETE).
 *   - Request es stateful (sesión activa vía host en
 *     SANCTUM_STATEFUL_DOMAINS).
 *
 * Para requests con Bearer token, no hay riesgo CSRF y se omite.
 */
class ValidateStatefulCsrf
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(strtoupper($request->method()), self::UNSAFE_METHODS, strict: true)) {
            return $next($request);
        }

        if (! $this->hasActiveSession($request)) {
            return $next($request);
        }

        $token = $this->getTokenFromRequest($request);

        if ($token === null || ! $this->tokensMatch($request, $token)) {
            abort(419, 'CSRF token mismatch.');
        }

        return $next($request);
    }

    private function hasActiveSession(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return $request->session()->isStarted();
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (! $token) {
            $header = $request->header('X-XSRF-TOKEN');
            if ($header) {
                try {
                    // 1. Decrypt: returns "PREFIX|TOKEN" (40+1+40 = 81 chars)
                    $decrypted = app(\Illuminate\Contracts\Encryption\Encrypter::class)
                        ->decrypt($header, false);

                    // 2. Strip the 40-char HMAC prefix + 1-char separator "|"
                    $token = CookieValuePrefix::remove($decrypted);
                } catch (DecryptException) {
                    $token = null;
                }
            }
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function tokensMatch(Request $request, string $token): bool
    {
        $session = $request->session();
        $sessionToken = $session->token();

        if (! is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals(known_string: $sessionToken, user_string: $token);
    }
}
