const API_URL = (import.meta.env.PUBLIC_API_URL ?? '').replace(/\/$/, '');

import { getXsrfToken } from './csrf';

type ApiErrorResponse = {
  message?: string;
  errors?: Record<string, string[]> | null;
};

export type ApiError = {
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
};

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
};

function resolveApiUrl(endpoint: string): string {
  if (!API_URL) {
    throw new Error('PUBLIC_API_URL is not configured.');
  }

  const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

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
  const xsrfToken = getXsrfToken();
  const shouldInjectXsrf = isStateChangingMethod(rest.method) && xsrfToken !== null;

  const requestHeaders: HeadersInit = {
    Accept: 'application/json',
    ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
    ...(shouldInjectXsrf ? { 'X-XSRF-TOKEN': xsrfToken! } : {}),
    ...headers,
  };

  let response: Response;
  try {
    response = await fetch(resolveApiUrl(endpoint), {
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
