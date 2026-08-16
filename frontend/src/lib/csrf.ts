/**
 * CSRF bootstrap para Sanctum stateful cookies (CR2).
 *
 * Sanctum usa CSRF "double-submit cookie": el backend setea una cookie
 * `XSRF-TOKEN` (httpOnly=false, NO protegida por httpOnly porque el JS
 * debe poder leerla) y el frontend la copia a un header `X-XSRF-TOKEN`
 * en cada POST stateful. El middleware ValidateCsrfToken compara header
 * vs cookie — si coinciden, request es legítimo.
 *
 * Flow:
 *   1. App boot → `bootstrapCsrf()` hace GET /sanctum/csrf-cookie.
 *      Backend emite `XSRF-TOKEN=...` (URL-encoded) en Set-Cookie.
 *   2. Este módulo decodifica la cookie y la cachea en `cachedToken`.
 *   3. `api.ts` llama `getXsrfToken()` y la inyecta como header en
 *      POST/PUT/DELETE/PATCH.
 *
 * Failure mode: si el primer POST corre ANTES del bootstrap, el backend
 * rechaza con 419 (CSRF mismatch). Por eso se invoca desde AuthLayout
 * mount, antes de cualquier submit — ver `src/layouts/AuthLayout.astro`.
 */

const CSRF_ENDPOINT = '/sanctum/csrf-cookie';

let cachedToken: string | null = null;

/**
 * Lee `cachedToken` — usado por `api.ts` para inyectar X-XSRF-TOKEN.
 *
 * Devuelve `null` antes del primer `bootstrapCsrf()` o si el backend
 * no emitió la cookie (ej: response 4xx antes de Sanctum stateful).
 */
export function getXsrfToken(): string | null {
  return cachedToken;
}

/**
 * Hace GET /sanctum/csrf-cookie con `credentials: 'include'` para que
 * el browser incluya/reciba cookies httpOnly del backend, decodifica
 * la cookie `XSRF-TOKEN` emitida por Sanctum, y la cachea para que
 * `getXsrfToken()` la devuelva.
 *
 * Idempotente: si ya hay un token cacheado, skip el fetch (re-bootstrap
 * sería un round-trip innecesario). Para forzar re-bootstrap, recargar
 * la página.
 */
export async function bootstrapCsrf(): Promise<void> {
  if (cachedToken !== null) {
    return;
  }

  await fetch(CSRF_ENDPOINT, {
    method: 'GET',
    credentials: 'include',
  });

  cachedToken = readXsrfTokenFromCookie();
}

/**
 * Lee `XSRF-TOKEN` desde `document.cookie` y la URL-decodifica.
 *
 * Sanctum emite la cookie URL-encoded (e.g. `abc%2Bdef%2F...`). El
 * header `X-XSRF-TOKEN` debe contener el valor RAW (decodificado) para
 * que el middleware backend haga match contra la cookie (que también
 * es la versión raw cuando Sanctum la compara).
 *
 * @returns token raw, o `null` si la cookie no existe.
 */
function readXsrfTokenFromCookie(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  if (match === null) {
    return null;
  }

  return decodeURIComponent(match[1]);
}
