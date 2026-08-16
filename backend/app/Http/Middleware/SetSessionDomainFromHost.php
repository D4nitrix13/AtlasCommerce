<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que deriva `config('session.domain')` desde el host de la request.
 *
 * Razón de existir: el backend panda-kitsune sirve 2 eTLD+1s distintos
 * (`panda-estampados.com` y `kitsune.com`). Un `SESSION_DOMAIN` único
 * vía `.env` leakearía sesión entre tenants — cookie de panda válida
 * en kitsune (¡vector de account takeover cross-tenant!). Con un valor
 * único por request, cada tenant queda aislado al nivel de cookie.
 *
 * Comportamiento:
 *   - `panda-estampados.com`             → `.panda-estampados.com`
 *   - `admin.panda-estampados.com`       → `.panda-estampados.com`
 *   - `kitsune.com`                      → `.kitsune.com`
 *   - `admin.kitsune.com`                → `.kitsune.com`
 *   - `panda-estampados.test` (dev)      → `.panda-estampados.test`
 *   - `localhost`                        → null (browser ignora Domain en localhost)
 *
 * El prefijo `.` cubre subdomains. Se ejecuta ANTES del session middleware
 * (registrado como `prepend` en `bootstrap/app.php`) para que el Set-Cookie
 * generado ya tenga el Domain correcto.
 */
final class SetSessionDomainFromHost
{
    /**
     * Root eTLD+1s del proyecto. Mapeo estático (no PSR-7 public-suffix-list):
     * los 4 tenants (prod + dev) están hardcodeados — auditable en código, sin dep.
     *
     * @var array<int, string>
     */
    private const ETLD1S = [
        'panda-estampados.com',
        'kitsune.com',
        'mm-boutique.com',
        'morales-store.com',
        'panda-estampados.test',
        'kitsune.test',
        'mm-boutique.test',
        'morales-store.test',
    ];

    /**
     * Hosts donde el browser ignora Domain attribute (solo `localhost`
     * puro, no `.test`/`.local` — esos sí soportan Domain correctamente).
     *
     * @var array<int, string>
     */
    private const LOCAL_HOSTS = ['localhost'];

    public function handle(Request $request, Closure $next): Response
    {
        $host = (string) $request->getHost();

        $etld1 = $this->resolveEtld1(host: $host);

        if ($etld1 !== null) {
            config(['session.domain' => '.' . $etld1]);
        }

        return $next($request);
    }

    /**
     * Resuelve el root eTLD+1 del host, o `null` si es host local-only.
     */
    private function resolveEtld1(string $host): ?string
    {
        $host = strtolower(string: $host);

        if (in_array(needle: $host, haystack: self::LOCAL_HOSTS, strict: true)) {
            return null;
        }

        foreach (self::ETLD1S as $etld1) {
            if ($host === $etld1 || str_ends_with(haystack: $host, needle: '.' . $etld1)) {
                return $etld1;
            }
        }

        return null;
    }
}