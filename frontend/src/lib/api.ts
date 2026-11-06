const API_URL = (import.meta.env.PUBLIC_API_URL ?? '').replace(/\/$/, '');

/**
 * Server-only absolute origin of the API, leída del environment del
 * proceso vía `import.meta.env`. Se usa cuando `PUBLIC_API_URL` es
 * relativo (`/api`) y la llamada se hace desde SSR — ahí `fetch()`
 * exige URL absoluta y `Astro.url.origin` no es suficiente en dev (el
 * dominio `*.test` solo lo resuelve Caddy en el host, no el container
 * de Astro).
 *
 * Configurar en compose como `INTERNAL_API_ORIGIN=http://nginx:8000`
 * (formato `host:puerto` del sidecar nginx) — esa env var es el
 * "puente" entre Astro y el backend vía la red Docker interna,
 * idéntica al patrón que `pages/[locale]/products/[slug].astro` ya
 * usa vía `process.env.INTERNAL_API_ORIGIN`.
 *
 * El browser nunca toca esta constante: `import.meta.env.X` para
 * X no-`PUBLIC_*` devuelve `undefined` en el bundle del cliente
 * (Astro bloquea la exposición por seguridad), por eso
 * `PUBLIC_API_URL=/api` y la resolución contra `window.location.origin`
 * siguen siendo los responsables de la llamada en el browser.
 *
 * Por qué el type cast: el default `ImportMetaEnv` de Astro solo
 * auto-tipa vars `PUBLIC_*`. Tipear `INTERNAL_API_ORIGIN` con un
 * `.d.ts` ambient se topa con la convención de Astro de declarar la
 * interface a top-level (no dentro de `declare namespace`) — el cast
 * localizado evita mantener un archivo de augmentación para una sola
 * variable. Si en el futuro se suman más vars privados siguiendo el
 * mismo patrón, conviene centralizar el cast o mover a un `.d.ts` con
 * `interface ImportMetaEnv { ... }` a top-level.
 */
// `import.meta.env` sólo expone lo que Vite/Astro decide filtrar, y las env
// del container que no están en `.env` no llegan por ahí: el cast devolvía
// `undefined` y la resolución caía al `origin` del request —un dominio
// `.test` que el container no puede resolver—. `process.env` sí las tiene en
// SSR, que es exactamente donde se usa.
const API_INTERNAL_URL = (
  (import.meta.env as { INTERNAL_API_ORIGIN?: string }).INTERNAL_API_ORIGIN ??
  (typeof process !== 'undefined' ? process.env.INTERNAL_API_ORIGIN : undefined) ??
  ''
).replace(/\/$/, '');

/**
 * Construye una URL completa del backend a partir de un endpoint path
 * (relativo a `PUBLIC_API_URL`). Usado para redirecciones de browser
 * (window.location.assign) donde no se puede usar apiFetch porque
 * necesitamos una URL completa, no una Response.
 *
 * Lanza si PUBLIC_API_URL no está configurado (mismo contrato que
 * `resolveApiUrl` abajo).
 */
export function buildApiUrl(endpoint: string): string {
  if (!API_URL) {
    throw new Error('PUBLIC_API_URL is not configured.');
  }
  const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  if (API_URL.startsWith('/')) {
    if (API_INTERNAL_URL === '') {
      throw new Error(
        'PUBLIC_API_URL is relative and INTERNAL_API_ORIGIN is not configured: ' +
          'buildApiUrl needs an absolute origin. Set INTERNAL_API_ORIGIN in the runtime environment ' +
          '(docker-compose env, .env.production, etc) or call apiFetch from the server.',
      );
    }
    return `${API_INTERNAL_URL}${API_URL}${normalizedEndpoint}`;
  }
  return `${API_URL}${normalizedEndpoint}`;
}

import { getXsrfToken, bootstrapCsrf } from './csrf';
import { getLocale } from '../i18n';

interface ApiErrorResponse {
  message?: string;
  errors?: Record<string, string[]> | null;
}

export interface ApiError {
  status: number;
  message: string;
  errors: Record<string, string[]> | null;
  /**
   * True cuando el error fue un fallo de red (fetch tiró TypeError
   * porque el backend está caído, CORS bloqueó, DNS falló, etc.).
   * Distinto de un HTTP 4xx/5xx (esos llevan isNetworkError: false).
   *
   * Los callers usan esto para decidir si limpian el estado de auth
   * (401/403 → token inválido) o lo preservan (network → el token
   * sigue siendo válido, solo no pudimos validarlo contra el backend).
   */
  isNetworkError: boolean;
}

type RequestOptions = Omit<RequestInit, 'body'> & {
  /**
   * @deprecated CR2 — la auth ahora viaja en cookies httpOnly; el
   * `token` field se ignora (Bearer injection removida). Se mantiene
   * la firma del API para no romper callers durante la transición,
   * pero no debería pasarse nada. Se eliminará en la limpieza post-
   * quarantine window (ver design doc §8).
   */
  token?: string | null;
  /**
   * Acepta un objeto plano (se serializa a JSON), FormData, o un
   * BodyInit nativo de fetch. El `Content-Type` se infiere: si es
   * FormData se omite (lo pone el browser con boundary), si no se
   * fuerza a `application/json`.
   */
  body?: BodyInit | Record<string, unknown> | unknown[] | null;
  /**
   * Origen contra el que resolver un `PUBLIC_API_URL` relativo desde
   * SSR.
   *
   * En el browser `fetch('/api/x')` resuelve contra el origen de la
   * página y funciona. En SSR —Astro renderizando en Node— `fetch`
   * exige una URL absoluta y una ruta relativa lanza
   * `TypeError: fetch() URL is invalid`, y la página se renderiza
   * vacía sin error visible.
   *
   * Resolución en este orden:
   *   1. `INTERNAL_API_ORIGIN` env var (env-var del proceso en SSR,
   *      composición típica docker-compose: `http://nginx:8000`).
   *      Es el camino feliz en dev: el container de Astro no resuelve
   *      dominios `.test` del host pero sí ve a `nginx` en la red
   *      Docker.
   *   2. `origin` explícito que pasa quien llama. Se usa cuando no
   *      hay env var (típicamente producción, donde Astro.url.origin
   *      ya resuelve al host público y no hace falta bypass).
   *   3. Throw con mensaje accionable — antes este código tragaba la
   *      falla y la página quedaba vacía "como bug de datos" cuando
   *      en realidad era bug de transporte.
   *
   * En el browser `origin` se ignora: el cliente usa el origin del
   * document y `PUBLIC_API_URL=/api` resuelve contra él.
   */
  origin?: string;
  /**
   * Override del header HTTP `Host`. Necesario cuando el SSR fetch se
   * hace vía `INTERNAL_API_ORIGIN` (sidecar nginx en la red Docker):
   * el backend resuelve el tenant por header `Host`, y el sidecar
   * expone su propio hostname (`nginx:8000`), no el dominio público
   * (`*.test`). Sin este override el backend responde 404
   * "Store not found or inactive".
   *
   * Los callers en SSR pasan `host: Astro.url.host` (solo el hostname,
   * no la URL completa) para que el middleware `store.domain` del
   * backend pueda resolver el tenant por la cabecera que llegó del
   * browser original.
   *
   * En el browser `host` se ignora: el navegador setea `Host` por su
   * cuenta, así que cualquier valor acá sería pisado por el real.
   */
  host?: string;
};

function resolveApiUrl(endpoint: string, origin?: string): string {
  if (!API_URL) {
    throw new Error('PUBLIC_API_URL is not configured.');
  }

  const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

  if (API_URL.startsWith('/')) {
    // Resolución en 3 pasos — ver docstring de `origin?` arriba.
    const resolvedOrigin =
      API_INTERNAL_URL !== ''
        ? API_INTERNAL_URL
        : origin !== undefined && origin !== ''
          ? origin
          : // Browser: el mismo origin de la página (con el proxy dev de
            // /api en Astro, y en prod detrás del mismo origin). Sin este
            // fallback todo apiFetch del cliente (login, galería admin)
            // revienta con "no origin was available".
            typeof window !== 'undefined' && window.location?.origin !== ''
            ? window.location.origin
            : null;
    if (resolvedOrigin === null) {
      throw new Error(
        'PUBLIC_API_URL is relative and no origin was available: it can only be ' +
          'resolved during SSR. Either set INTERNAL_API_ORIGIN in the runtime ' +
          'environment, or pass `origin` (e.g. Astro.url.origin) when fetching ' +
          'from the server.',
      );
    }

    return `${resolvedOrigin.replace(/\/$/, '')}${API_URL}${normalizedEndpoint}`;
  }

  return `${API_URL}${normalizedEndpoint}`;
}

async function parseJsonResponse<T>(response: Response): Promise<T> {
  if (response.status === 204) {
    return null as T;
  }

  const contentType = response.headers.get('content-type');

  if (!contentType?.includes('application/json')) {
    return null as T;
  }

  return response.json() as Promise<T>;
}

async function parseApiError(response: Response): Promise<ApiError> {
  const contentType = response.headers.get('content-type');

  let error: ApiErrorResponse | null = null;

  if (contentType?.includes('application/json')) {
    error = await response.json().catch(() => null);
  }

  return {
    status: response.status,
    message: error?.message ?? 'API request failed',
    errors: error?.errors ?? null,
    isNetworkError: false,
  };
}

/**
 * Determina si la request es state-changing y debe llevar X-XSRF-TOKEN.
 *
 * Sanctum's ValidateCsrfToken middleware solo chequea CSRF en métodos
 * state-changing (POST/PUT/DELETE/PATCH). GET/HEAD/OPTIONS son safe y
 * no necesitan token.
 */
function isStateChangingMethod(method: string | undefined): boolean {
  if (method === undefined) {
    return false;
  }
  const upper = method.toUpperCase();
  return upper === 'POST' || upper === 'PUT' || upper === 'DELETE' || upper === 'PATCH';
}

export async function apiFetch<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
  const { headers, body, ...rest } = options;

  const isFormData = body instanceof FormData;
  const isPlainObject =
    body !== null && body !== undefined && typeof body === 'object' && !isFormData;

  const serializedBody: BodyInit | null | undefined = isPlainObject ? JSON.stringify(body) : body;

  // CR2: CSRF token en POST/PUT/DELETE/PATCH (NO en GET — Sanctum no
  // lo chequea y agregar header en GET solo agrega ruido al preflight).
  //
  // Si la cookie XSRF-TOKEN no está seteada todavía (AuthLayout acaba
  // de montar y el inline script del CSR cookie está en flight), esperamos
  // a que bootstrapCsrf() la setee antes de mandar la request. Sin esto
  // el backend responde 419 CSRF token mismatch en el primer POST
  // post-mount. Solo agrega latencia al primer POST (~50ms); los
  // siguientes son instantáneos porque la cookie ya está.
  //
  // IMPORTANTE: getXsrfToken() lee `document.cookie` (browser only).
  // En SSR (Node.js) `document` no existe. Por eso el check
  // isStateChangingMethod(rest.method) DEBE ir primero en cortocircuito:
  // para GET/HEAD/OPTIONS ni siquiera llamamos getXsrfToken().
  const xsrfToken = isStateChangingMethod(rest.method) ? getXsrfToken() : null;
  if (xsrfToken === null && isStateChangingMethod(rest.method)) {
    await bootstrapCsrf();
  }
  const shouldInjectXsrf = xsrfToken !== null;

  // `Record` y no `HeadersInit`: hace falta asignar `Host` después de armarlo,
  // y `HeadersInit` es una unión de iterables/tuplas donde la asignación por
  // clave no compila. `Headers` (la clase) también sirve para `fetch`, pero
  // el cast a `HeadersInit` que devuelve fetch igual acepta este objeto plano.
  const requestHeaders: Record<string, string> = {
    Accept: 'application/json',
    // Locale de la UI activa: el backend localiza los mensajes de
    // validación según esta cabecera (middleware X-Locale).
    'X-Locale': getLocale(),
    ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
    ...(shouldInjectXsrf ? { 'X-XSRF-TOKEN': xsrfToken! } : {}),
    ...(headers as Record<string, string> | undefined),
  };

  // SSR multi-tenant: cuando el fetch va por `INTERNAL_API_ORIGIN`
  // (sidecar nginx en docker network) hace falta restaurar el `Host`
  // original para que el backend pueda resolver el tenant vía
  // middleware `store.domain`. Ver docstring de `host?` arriba. Sin
  // esto el backend responde 404 porque su `Host` header es `nginx`,
  // no el dominio público del tenant.
  if (options.host !== undefined && options.host !== '') {
    requestHeaders.Host = options.host;
  }

  let response: Response;
  try {
    response = await fetch(resolveApiUrl(endpoint, options.origin), {
      ...rest,
      credentials: 'include',
      body: serializedBody,
      headers: requestHeaders,
    });
  } catch (error) {
    // fetch() rechaza con TypeError cuando la red cae (backend down,
    // CORS, DNS, offline). Lo envolvemos como ApiError con
    // isNetworkError: true para que los callers (auth.hydrate en
    // particular) puedan distinguir "red caída" de "401 expirado".
    const message =
      error instanceof Error && error.message !== '' ? error.message : 'Network error';
    const networkError: ApiError = {
      status: 0,
      message,
      errors: null,
      isNetworkError: true,
    };
    throw networkError;
  }

  if (!response.ok) {
    throw await parseApiError(response);
  }

  return parseJsonResponse<T>(response);
}
