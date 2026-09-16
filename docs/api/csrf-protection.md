# CSRF Protection — Convenciones para APIs stateful

> **RNF-SEC-018**: Las rutas web stateful requieren CSRF; las rutas API con
> Bearer token NO.

## Contexto

El backend soporta dos modos de autenticación simultáneamente:

1. **Stateless** (mobile apps, server-to-server, integraciones): `Authorization: Bearer <token>`. Sanctum valida el token y la request NO usa sesión.
2. **Stateful** (frontend SPA via cookies httpOnly): el navegador envía `Cookie: XSRF-TOKEN=...` y la request pasa por `StartSession` + Sanctum session auth.

El modo se determina por el `Host` header contra `SANCTUM_STATEFUL_DOMAINS` (en `.env`). Hosts en esa lista reciben sesión; el resto se trata como stateless.

## Riesgo CSRF

Con auth stateful (cookie httpOnly), un atacante en otro dominio podría:
- Hacer que el navegador del usuario autenticado envíe una request
  POST a una URL stateful de nuestro backend.
- Si NO validamos CSRF, esa request se procesa como si fuera del
  usuario legítimo.

Sanctum documenta este riesgo y recomienda validar CSRF en todas las
rutas stateful que mutan estado. La forma estándar es el middleware
`Illuminate\Foundation\Http\Middleware\ValidateCsrfToken`, pero ese
middleware valida TODAS las requests con sesión — incluyendo requests
con Bearer token donde no hay riesgo CSRF.

## Nuestra solución: `ValidateStatefulCsrf`

El middleware custom `App\Http\Middleware\ValidateStatefulCsrf`:

1. **Skipea métodos seguros** (GET, HEAD, OPTIONS) — no hay riesgo.
2. **Skipea requests no-stateful** (sin sesión) — son Bearer token,
   Sanctum ya las trata como stateless.
3. **Valida CSRF solo cuando hay sesión activa + método mutating**.

```php
// Solo se ejecuta para requests stateful con método mutating.
if (method !== POST/PUT/PATCH/DELETE) return $next($request);
if (! hasActiveSession) return $next($request); // Bearer token

// Validar X-XSRF-TOKEN (header) contra _token (session).
if (! hash_equals($session->token(), $request->header('X-XSRF-TOKEN'))) {
    abort(419);
}
```

## Patrón de tokens

Laravel sincroniza automáticamente el cookie `XSRF-TOKEN` con el header
`X-XSRF-TOKEN`:

```js
// Frontend (axios con `withCredentials: true`):
axios.get('/sanctum/csrf-cookie').then(() => {
    // Ahora la cookie XSRF-TOKEN está seteada.
    axios.post('/api/v1/auth/login', {...});
});
```

O via `fetch`:

```ts
fetch('/api/v1/auth/login', {
    credentials: 'include',
    headers: { 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') },
});
```

## Configuración

```php
// bootstrap/app.php — API group middleware:
$middleware->api(prepend: [
    SetSessionDomainFromHost::class,
    EnsureFrontendRequestsAreStateful::class,
    ValidateStatefulCsrf::class,        // ← CSRF condicional
]);
```

`ValidateStatefulCsrf` corre DESPUÉS de `EnsureFrontendRequestsAreStateful`,
así la sesión ya está iniciada cuando mi middleware evalúa.

## Tests

`tests/Feature/Http/ValidateStatefulCsrfTest.php` cubre:

- GET sin CSRF pasa.
- POST con Bearer token (stateless) pasa sin CSRF.
- POST a host no stateful pasa sin CSRF.
- POST/PUT/DELETE a host stateful sin sesión → 401 (auth), no 419.

Los tests con sesión real requieren `RefreshDatabase` + session driver
que persista. En CI testing con `SESSION_DRIVER=array`, esos tests
demuestran que el middleware NO se aplica agresivamente. La verificación
end-to-end con sesión real se hace via script de debug
(`backend/test_cmw*.php`) y manualmente con el navegador.

## Excepciones

Si necesitás una ruta stateful que SKIPEE CSRF explícitamente
(raro pero válido para webhooks internos con IP allowlist):

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/api/v1/internal/webhook', ...)->withoutMiddleware([
        \App\Http\Middleware\ValidateStatefulCsrf::class,
    ]);
});
```

## Referencias

- [Sanctum — SPA Authentication](https://laravel.com/docs/11.x/sanctum#spa-authentication)
- [OWASP CSRF Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- PR #99 — implementación del middleware + tests.
