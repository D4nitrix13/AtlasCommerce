<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Helpers para construir la URL de retorno del callback OAuth.
 *
 * El callback de cada provider puede fallar por distintos motivos
 * (email_conflict, state_mismatch, etc.) y tiene que redirigir al
 * usuario al login page del tenant del que vino. La URL de retorno
 * depende del host (admin vs account) y del locale prefix.
 *
 * Esta clase encapsula esa lógica para que el controller no tenga
 * que hacer regex matching inline. Diseñada en PR 2a para mantener
 * el controller de PR 2b enfocado en el flow de autenticación.
 */
final class OAuthRedirectTarget
{
    /**
     * Extrae el prefijo de locale de una ruta, si existe.
     *
     * Reglas:
     *   - `/es/admin/auth/login`  → `es`
     *   - `/en/account/login`     → `en`
     *   - `/admin/auth/login`     → `null` (sin locale)
     *   - `/`                     → `null`
     *
     * El locale válido es cualquier segmento de path de 2 letras
     * lowercase que matchee uno de los locales configurados en
     * `config('app.available_locales')` (default: `['es', 'en']`).
     */
    public static function extractLocaleFromPath(string $path): ?string
    {
        $availableLocales = (array) config(key: 'app.available_locales', default: ['es', 'en']);

        if (! str_starts_with($path, '/')) {
            return null;
        }

        $segments = explode('/', $path);
        if (count($segments) >= 2
            && in_array($segments[1], $availableLocales, strict: true)) {
            return $segments[1];
        }

        return null;
    }

    /**
     * Quita el prefijo de locale de una ruta.
     *
     * Reglas:
     *   - `/es/admin/auth/login`  → `/admin/auth/login`
     *   - `/en/account/login`     → `/account/login`
     *   - `/admin/auth/login`     → `/admin/auth/login` (sin locale, unchanged)
     *   - `/`                     → `/`
     *
     * El locale válido es cualquier segmento de path de 2 letras
     * lowercase que matchee uno de los locales configurados en
     * `config('app.available_locales')` (default: `['es', 'en']`).
     *
     * @param  string  $path  Ruta absoluta del request path.
     * @return string Ruta sin el segmento de locale.
     */
    public static function stripLocaleFromPath(string $path): string
    {
        $availableLocales = (array) config(key: 'app.available_locales', default: ['es', 'en']);

        if (! str_starts_with($path, '/')) {
            // Relative path — devolvemos igual (no debería pasar en
            // callback URLs pero por seguridad).
            return $path;
        }

        $segments = explode('/', $path);
        // ['', 'es', 'admin', 'auth', 'login']
        // ['es', 'admin', ...] cuando hay locale
        if (count($segments) >= 2
            && in_array($segments[1], $availableLocales, strict: true)) {
            array_splice($segments, 1, 1);
        }

        return implode('/', $segments);
    }

    /**
     * Determina si el host es un subdomain admin.
     *
     * Reglas:
     *   - `admin.panda-estampados.com` → true
     *   - `panda-estampados.com`       → false
     *   - `localhost`                  → false (admin no aplica en dev local)
     *
     * Match contra el prefijo `admin.` en el subdominio — mismo
     * criterio que `SetSessionDomainFromHost` espera del routing
     * de Caddy (admin.* para backoffice).
     */
    public static function isAdminHost(string $host): bool
    {
        $host = strtolower(string: $host);

        return str_starts_with($host, 'admin.');
    }

    /**
     * ¿El path corresponde a una página de login (no de signup)?
     *
     * Usado por el callback para distinguir el intent del flow: desde el
     * login, un email ya registrado es lo esperado y se vincula la
     * identidad; desde el signup es un conflicto.
     */
    public static function isLoginPath(string $path): bool
    {
        $bare = self::stripLocaleFromPath($path);

        return str_ends_with($bare, '/admin/auth/login') || str_ends_with($bare, '/account/login');
    }

    /**
     * Construye el path de login al que redirigir en failure.
     *
     * Combina `stripLocaleFromPath` + decisión admin/account según
     * el host. Usado por el controller en el catch block cuando
     * alguna `OAuthException` se dispara.
     *
     * IMPORTANTE: preserva el locale prefix del path original. Si el
     * browser estaba en `/es/admin/auth/login` cuando inició el flow,
     * el callback failure redirect vuelve a `/es/admin/auth/login`,
     * no a `/admin/auth/login`. Sin esto, el browser concatena
     * relativo y termina en `/es/admin/admin/auth/login` (404).
     */
    public static function fromRequest(string $originalPath, string $host): string
    {
        $locale = self::extractLocaleFromPath($originalPath);
        $path = self::stripLocaleFromPath($originalPath);
        $loginSegment = self::isAdminHost(host: $host) ? '/admin/auth/login' : '/account/login';

        // Si el path original ya termina en el login URL, lo
        // respetamos (caso normal: redirect desde el login page).
        // Si no (caso raro: redirect desde una URL arbitraria),
        // caemos al login del tenant del host. `/account/auth/login`
        // NO entra al primer grupo: esa forma (con `/auth/` de más) no
        // tiene página y 404ea, así que se reemplaza por la canónica.
        if (str_ends_with($path, '/admin/auth/login') || str_ends_with($path, '/account/login')) {
            $result = $path;
        } else {
            $result = $loginSegment;
        }

        // El router del frontend exige `/<locale>/` en toda ruta (Astro
        // i18n con `prefixDefaultLocale: true`). Sin este fallback, un
        // session target vacío producía `/account/login` sin prefijo, y
        // el usuario caía en un redirect extra antes del login.
        $locale ??= (string) (((array) config(key: 'app.available_locales', default: ['es', 'en']))[0] ?? 'es');

        return '/'.$locale.$result;
    }
}
