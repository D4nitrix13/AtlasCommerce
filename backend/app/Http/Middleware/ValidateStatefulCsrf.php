<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CSRF para rutas API stateful (cookie auth, RF-SEC-018).
 *
 * Aplica validación CSRF solo cuando:
 *  1. El método HTTP es state-mutating (POST/PUT/PATCH/DELETE).
 *  2. La request es stateful — es decir, tiene sesión activa
 *     (host en SANCTUM_STATEFUL_DOMAINS vía EnsureFrontendRequestsAreStateful).
 *
 * Para requests con Bearer token (mobile apps, server-to-server),
 * Sanctum NO usa cookie auth, por lo tanto no hay riesgo CSRF y el
 * check se omite.
 *
 * Sanctum recomienda usar el middleware estándar `ValidateCsrfToken`
 * de Laravel, pero ese valida TODAS las requests con sesión. Como acá
 * muchas rutas API reciben tanto Bearer como cookie auth, necesitamos
 * este middleware que solo aplica a las stateful.
 *
 * El frontend envía el token CSRF en:
 *  - Header `X-XSRF-TOKEN` (decodificado del cookie `XSRF-TOKEN`).
 *  - Campo `_token` en form data.
 *
 * Laravel automáticamente sincroniza el cookie con el header via
 * `withCredentials: true` en axios o `credentials: 'include'` en fetch.
 */
class ValidateStatefulCsrf
{
    /**
     * Métodos HTTP que mutan estado y requieren CSRF.
     */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        // Sólo métodos state-mutating requieren CSRF.
        if (! in_array(strtoupper($request->method()), self::UNSAFE_METHODS, strict: true)) {
            return $next($request);
        }

        // Si la request NO tiene sesión (Bearer token), no es stateful,
        // Sanctum usa token auth y CSRF no aplica.
        if (! $this->hasActiveSession($request)) {
            return $next($request);
        }

        // A partir de acá, es una request stateful con método mutating:
        // validar token CSRF estándar de Laravel (mismo algoritmo que
        // Illuminate\Foundation\Http\Middleware\ValidateCsrfToken).
        $token = $this->getTokenFromRequest($request);

        if ($token === null || ! $this->tokensMatch($request, $token)) {
            abort(419, 'CSRF token mismatch.');
        }

        return $next($request);
    }

    private function hasActiveSession(Request $request): bool
    {
        // Si EnsureFrontendRequestsAreStateful corrió, hay sesión.
        // Verificamos via el session store (lo agrega StartSession).
        if (! $request->hasSession()) {
            return false;
        }

        $session = $request->session();

        return $session->isStarted();
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        // Prioridad: header X-XSRF-TOKEN (patrón Sanctum).
        $token = $request->header(key: 'X-XSRF-TOKEN');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        // Fallback: campo _token en form data (estándar Laravel).
        $token = $request->input(key: '_token');

        return is_string($token) ? $token : null;
    }

    /**
     * Compara el token enviado con el token de la sesión.
     *
     * Usa hash_equals para timing-safe comparison (mismo algoritmo que
     * el middleware nativo de Laravel).
     */
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
