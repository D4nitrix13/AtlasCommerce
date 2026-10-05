import { useSyncExternalStore } from 'react';
import type {
  LoginPayload,
  LoginResponse,
  LoginResult,
  TwoFactorChallenge,
  TwoFactorVerifyPayload,
  User,
} from '../types/auth';
import { apiFetch } from '../lib/api';
import { API_ENDPOINTS } from '../lib/endpoints';

/**
 * Store de autenticación (React 19 + useSyncExternalStore).
 *
 * Sin dependencias externas: implementa el patrón subscribe/getSnapshot
 * manualmente. El user se rehidrata via /auth/me al boot usando la
 * cookie de sesión httpOnly (CR2 — Sanctum stateful).
 *
 * Antes del CR2, el token se persistía en `localStorage` con key
 * `auth.token` (vulnerable a XSS). Ahora la auth vive en cookies
 * httpOnly que el browser maneja — JS no puede leer/escribir el token.
 *
 * Uso:
 *   const { user, login, logout, isAuthenticated } = useAuth();
 */

interface AuthState {
  /**
   * @deprecated CR2 — siempre null (token en cookie httpOnly). Mantenido
   * en el shape por compat con callers (se eliminará en cleanup post-
   * quarantine window — ver design doc §8).
   */
  token: string | null;
  user: User | null;
  isHydrating: boolean;
}

const initialState: AuthState = {
  token: readTokenFromStorage(),
  user: null,
  // CR2: arrancamos con isHydrating=true para que los componentes que
  // dependen de la auth (AdminHomeRedirect, AuthGuard) NO redirijan
  // antes de que hydrate() haya llamado a /api/v1/auth/me. El set
  // isHydrating=false al final de hydrate() despierta a esos effects.
  isHydrating: true,
};

/**
 * Lee el store_id activo del localStorage.
 *
 * Se persiste en login/register para que el frontend sepa a qué tienda
 * el usuario está atado sin tener que hidratar `/auth/me` en cada
 * navegación. Backend sigue siendo la fuente de verdad (validado vía
 * Sanctum en cada request).
 */
const STORE_ID_STORAGE_KEY = 'auth.store_id';

export function getActiveStoreId(): number | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const raw = window.localStorage.getItem(STORE_ID_STORAGE_KEY);
  if (raw === null) {
    return null;
  }

  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) ? parsed : null;
}

function writeActiveStoreId(storeId: number | null): void {
  if (typeof window === 'undefined') {
    return;
  }

  if (storeId === null) {
    window.localStorage.removeItem(STORE_ID_STORAGE_KEY);
  } else {
    window.localStorage.setItem(STORE_ID_STORAGE_KEY, String(storeId));
  }
}

let state: AuthState = initialState;
const listeners = new Set<() => void>();

function emit(): void {
  for (const listener of listeners) {
    listener();
  }
}

function readTokenFromStorage(): string | null {
  // CR2: Sanctum stateful usa cookies httpOnly (no JS-readable). El
  // token ya NO vive en localStorage → esta función siempre devuelve
  // null. Se mantiene la firma para no romper callers durante la
  // ventana de transición (se eliminará en la limpieza post-quarantine
  // window — ver design doc §8).
  return null;
}

function writeTokenToStorage(_token: string | null): void {
  // CR2: Sanctum stateful usa cookies httpOnly manejadas por el
  // browser. JS no debe poder leer/escribir el token (XSS-safe).
  // Esta función es no-op — los callers siguen invocándola para
  // preservar el shape del flujo (login → writeTokenToStorage → no-op,
  // logout → writeTokenToStorage(null) → no-op), pero localStorage ya
  // no se toca.
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

function getSnapshot(): AuthState {
  return state;
}

export function getAuthToken(): string | null {
  return state.token;
}

export function getAuthUser(): User | null {
  return state.user;
}

export function isAuthenticated(): boolean {
  // CR2: la auth es via cookie httpOnly de Sanctum. JS no puede leer
  // el token raw, asi que `state.token` es null. Pero `state.user` se
  // hidrata via /api/v1/auth/me con credentials: 'include' (que envia
  // la cookie automaticamente). Si user esta presente, estamos authed.
  return state.user !== null || state.token !== null;
}

/**
 * Aplica el lado success del discriminated union de login.
 *
 * Usado por:
 *   - `login()` cuando el backend responde con `{ token, user }`.
 *   - El form 2FA después de un `verifyTotpChallenge` exitoso
 *     (mismo shape porque el backend re-emite token+user).
 *
 * Mantiene un solo punto de escritura del token para que
 * el logout y el hydrate puedan limpiarlo consistentemente.
 */
function applySession(response: LoginResponse): void {
  // CR2: token NO persiste en localStorage (writeTokenToStorage es
  // no-op). Se mantiene el shape del state.token para no romper
  // callers durante la ventana de transición (Bearer compat).
  state = { ...state, token: response.token, user: response.user };
  writeTokenToStorage(response.token);
  writeActiveStoreId(response.user.store_ids[0] ?? null);
  emit();
}

/**
 * Setea la sesi\u00f3n a partir de una respuesta de auth (login o register).
 *
 * Usado por el flujo de register: el endpoint /auth/register ya
 * devuelve { token, user }, no necesitamos hacer un segundo POST
 * a /auth/login para obtener el token. Esta funci\u00f3n lo guarda
 * directamente en el store + localStorage.
 */
export function setSession(response: LoginResponse): void {
  applySession(response);
}

/**
 * Login con discriminated union en la respuesta.
 *
 *   - HTTP 200 + { token, user }                  \u2192 { kind: 'success', ... }
 *   - HTTP 200 + { status: '2fa_required', ... }  \u2192 { kind: '2fa_required', ... }
 *   - HTTP 4xx/5xx                                 \u2192 throw ApiError
 *
 * En el caso `2fa_required` NO se escribe token ni user en el store
 * (eso lo hace el caller llamando `setSession` despu\u00e9s del verify).
 */
async function login(payload: LoginPayload): Promise<LoginResult> {
  const response = await apiFetch<LoginResponse & Partial<TwoFactorChallenge>>(
    API_ENDPOINTS.auth.login,
    {
      method: 'POST',
      body: payload as unknown as Record<string, unknown>,
    }
  );

  if (isTwoFactorChallenge(response)) {
    return {
      kind: '2fa_required',
      challengeId: response.challenge_token,
      method: response.method,
      expiresAt: response.expires_at,
    };
  }

  applySession(response);

  return {
    kind: 'success',
    token: response.token,
    user: response.user,
  };
}

/**
 * Verifica un challenge 2FA (TOTP) y devuelve el mismo discriminated
 * union que `login()`: si el backend responde success, persiste la
 * sesi\u00f3n y devuelve `{ kind: 'success' }`; si responde con un nuevo
 * challenge (raro, pero el backend podr\u00eda emitir uno nuevo), lo
 * devuelve como `{ kind: '2fa_required' }` para que el form lo muestre.
 */
async function verifyTotpChallenge(payload: TwoFactorVerifyPayload): Promise<LoginResult> {
  const response = await apiFetch<LoginResponse & Partial<TwoFactorChallenge>>(
    API_ENDPOINTS.auth.twoFactor.totp.verify as never,
    {
      method: 'POST',
      body: {
        challenge_token: payload.challengeId,
        code: payload.code,
      },
    }
  );

  if (isTwoFactorChallenge(response)) {
    return {
      kind: '2fa_required',
      challengeId: response.challenge_token,
      method: response.method,
      expiresAt: response.expires_at,
    };
  }

  applySession(response);

  return {
    kind: 'success',
    token: response.token,
    user: response.user,
  };
}

function isTwoFactorChallenge(
  response: LoginResponse & Partial<TwoFactorChallenge>
): response is LoginResponse &
  Partial<TwoFactorChallenge> & {
    status: '2fa_required';
    challenge_token: number;
    method: 'totp';
    expires_at: string;
  } {
  return (
    (response as { status?: string }).status === '2fa_required' &&
    typeof (response as { challenge_token?: unknown }).challenge_token === 'number'
  );
}

async function logout(): Promise<void> {
  try {
    // CR2: Sanctum stateful — la sesión viaja en cookie httpOnly. El
    // endpoint /auth/logout cierra la sesión del web guard y rota el
    // session ID. NO requiere Authorization header (la middleware
    // auth:sanctum acepta sesión o token; Sanctum stateful envía
    // automáticamente la cookie).
    await apiFetch(API_ENDPOINTS.auth.logout, {
      method: 'POST',
    });
  } catch {
    // Ignorar errores de logout (sesión expirada, network, etc.).
    // El objetivo es limpiar el estado local de todas formas.
  }

  state = { ...state, token: null, user: null };
  writeTokenToStorage(null);
  writeActiveStoreId(null);
  emit();
}

/**
 * Rehidrata el user desde el backend usando la cookie de sesión httpOnly.
 * Llamar al boot de la app para validar que la sesión sigue vigente.
 *
 * CR2: ya NO consultamos localStorage para el token — la auth viaja
 * en la cookie de sesión (Sanctum stateful). `credentials:'include'`
 * en apiFetch hace que el browser envíe la cookie automáticamente.
 *
 * Distingue entre errores de auth y errores de red:
 *   - HTTP 401 o 403 → sesión inválida/expirada → limpiar store_id + user.
 *   - Cualquier otro throw (incluido network: isNetworkError=true,
 *     HTTP 5xx) → conservar store_id + user, seguir offline-tolerant.
 */
async function hydrate(): Promise<void> {
  state = { ...state, isHydrating: true };
  emit();

  try {
    const response = await apiFetch<{ user: User }>(API_ENDPOINTS.auth.me);
    state = { ...state, user: response.user, isHydrating: false };
    writeActiveStoreId(response.user.store_ids[0] ?? null);
  } catch (error) {
    const apiError = error as {
      status?: number;
      isNetworkError?: boolean;
    };

    // Limpiar sesión SOLO cuando el backend nos rechaza la auth.
    // Si el backend está caído o devuelve 5xx, conservamos la sesión:
    // la cookie sigue siendo criptográficamente válida y queremos
    // reintentar en la próxima navegación, no forzar logout.
    const isAuthFailure = apiError.status === 401 || apiError.status === 403;

    if (isAuthFailure) {
      state = { ...state, user: null, isHydrating: false };
      writeActiveStoreId(null);
    } else {
      // Network down o 5xx — preservamos sesión (cookie httpOnly).
      // Solo log para diagnóstico: el usuario sigue "logueado" en
      // el cliente, pero la próxima navegación intentará rehidratar.
      if (typeof console !== 'undefined') {
        console.warn(
          `[auth.hydrate] Conservando sesión (no se pudo validar contra el backend). status=${apiError.status ?? 'unknown'} isNetworkError=${apiError.isNetworkError === true}`
        );
      }
      state = { ...state, isHydrating: false };
    }
  }

  emit();
}

export const authStore = {
  login,
  logout,
  hydrate,
  setSession,
  verifyTotpChallenge,
  subscribe,
  getSnapshot,
};

export function useAuth(): {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isHydrating: boolean;
  login: (payload: LoginPayload) => Promise<LoginResult>;
  logout: () => Promise<void>;
  setSession: typeof setSession;
  verifyTotpChallenge: typeof verifyTotpChallenge;
} {
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  return {
    user: snapshot.user,
    token: snapshot.token,
    // CR2: la auth es via cookie httpOnly de Sanctum. `snapshot.token`
    // es null (no se puede leer la cookie desde JS), pero `snapshot.user`
    // se hidrata via /api/v1/auth/me. Si user esta presente, authed.
    isAuthenticated: snapshot.user !== null || snapshot.token !== null,
    isHydrating: snapshot.isHydrating,
    login,
    logout,
    setSession,
    verifyTotpChallenge,
  };
}
