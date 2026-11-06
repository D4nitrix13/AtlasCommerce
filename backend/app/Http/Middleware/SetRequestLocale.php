<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que setea el locale de la app según el idioma del request.
 *
 * Razón de existir: los mensajes de validación (422) y los mensajes de
 * auth del backend salían hardcodeados o en el default inglés de Laravel
 * ("The email field is required."), ignorando el idioma de la interfaz.
 * Con el locale resuelto por request, `trans()` y el validator devuelven
 * los mensajes en el idioma que el cliente pidió (es/en).
 *
 * Resolución del locale (primer match gana):
 *   1. Header `X-Locale` (primer valor, ej: `X-Locale: en`).
 *   2. Primary tag de `Accept-Language` (`en-US,en;q=0.9` → `en`).
 *   3. Default `es` (locale primario del storefront).
 *
 * El candidato se lowercasa y se valida contra
 * `config('app.available_locales', ['es', 'en'])`; si no es válido se
 * usa el default `es`. Recién ahí se aplica `App::setLocale()`.
 *
 * Registrada en el grupo `api` de `bootstrap/app.php`, después de
 * `SetSessionDomainFromHost` y `EnsureFrontendRequestsAreStateful`
 * (el locale no depende de la sesión ni del tenant; solo del header),
 * y siempre antes de que el validator y los controllers traduzcan.
 */
final class SetRequestLocale
{
    /**
     * Locale default del storefront cuando el request no trae uno válido.
     */
    private const DEFAULT_LOCALE = 'es';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale(request: $request));

        return $next($request);
    }

    /**
     * Resuelve el locale del request: `X-Locale` → `Accept-Language`
     * (primary tag) → default `es`, validado contra los locales
     * disponibles.
     */
    private function resolveLocale(Request $request): string
    {
        $candidate = $request->header(key: 'X-Locale');

        if ($candidate === null || trim(string: (string) $candidate) === '') {
            $candidate = $this->primaryTag(
                acceptLanguage: (string) $request->header(key: 'Accept-Language', default: ''),
            );
        }

        $candidate = strtolower(string: trim(string: (string) $candidate));

        $availableLocales = (array) config(key: 'app.available_locales', default: ['es', 'en']);

        if ($candidate !== '' && in_array(needle: $candidate, haystack: $availableLocales, strict: true)) {
            return $candidate;
        }

        return self::DEFAULT_LOCALE;
    }

    /**
     * Extrae el primary tag del header `Accept-Language`
     * (`en-US,en;q=0.9` → `en`, `es-AR;q=0.8` → `es`).
     */
    private function primaryTag(string $acceptLanguage): string
    {
        $firstRange = explode(separator: ',', string: $acceptLanguage)[0];
        $withoutParams = explode(separator: ';', string: $firstRange)[0];

        return explode(separator: '-', string: $withoutParams)[0];
    }
}
