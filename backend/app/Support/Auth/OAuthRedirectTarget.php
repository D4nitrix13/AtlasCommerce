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
     * Quita el prefijo de locale de una ruta.
     *
     * Reglas:
     *   - `/es/admin/auth/login`  → `/admin/auth/login`
     *   - `/en/account/auth/login` → `/account/auth/login`
     *   - `/admin/auth/login`     → `/admin/auth/login` (sin locale, unchanged)
     *   - `/`                     → `/`
     *
     * El locale válido es cualquier segmento de path de 2 letras
     * lowercase que matchee uno de los locales configurados en
     * `config('app.available_locales')` (default: `['es', 'en']`).
     *
     * @param  string  $path  Ruta absoluta del request path.
     * @return string  Ruta sin el segmento de locale.
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
     * Construye el path de login al que redirigir en failure.
     *
     * Combina `stripLocaleFromPath` + decisión admin/account según
     * el host. Usado por el controller en el catch block cuando
     * alguna `OAuthException` se dispara.
     */
    public static function fromRequest(string $originalPath, string $host): string
    {
        $path = self::stripLocaleFromPath($originalPath);
        $loginSegment = self::isAdminHost(host: $host) ? '/admin/auth/login' : '/account/auth/login';

        // Si el path original ya termina en el login URL, lo
        // respetamos (caso normal: redirect desde el login page).
        // Si no (caso raro: redirect desde una URL arbitraria),
        // caemos al login del tenant del host.
        if (str_ends_with($path, '/admin/auth/login') || str_ends_with($path, '/account/auth/login')) {
            return $path;
        }

        return $loginSegment;
    }
}
