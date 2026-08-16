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
 * manualmente. El token se persiste en `localStorage` para sobrevivir
 * recargas; el user se rehidrata via /auth/me al boot.
 *
 * Uso:
 *   const { user, token, login, logout, isAuthenticated } = useAuth();
 */

const TOKEN_STORAGE_KEY = 'auth.token';

interface AuthState {
  token: string | null;
  user: User | null;
  isHydrating: boolean;
}

const initialState: AuthState = {
  token: readTokenFromStorage(),
  user: null,
  isHydrating: false,
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
  if (typeof window === 'undefined' || window.localStorage === undefined) {
    return null;
  }

  try {
    return window.localStorage.getItem(TOKEN_STORAGE_KEY);
  } catch {
    return null;
  }
}

function writeTokenToStorage(token: string | null): void {
  if (typeof window === 'undefined') {
    return;
  }

  if (token === null) {
    window.localStorage.removeItem(TOKEN_STORAGE_KEY);
  } else {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
  }
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
  return state.token !== null;
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
      body: payload,
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
    API_ENDPOINTS.auth.twoFactor.totp.verifyLogin,
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
    if (state.token !== null) {
      await apiFetch(API_ENDPOINTS.auth.logout, {
        method: 'POST',
        token: state.token,
      });
    }
  } catch {
    // Ignorar errores de logout (token expirado, network, etc.).
    // El objetivo es limpiar el estado local de todas formas.
  }

  state = { ...state, token: null, user: null };
  writeTokenToStorage(null);
  writeActiveStoreId(null);
  emit();
}

/**
 * Rehidrata el user desde el backend usando el token guardado.
 * Llamar al boot de la app para validar que el token sigue vigente.
 */
async function hydrate(): Promise<void> {
  const token = readTokenFromStorage();

  if (token === null) {
    state = { token: null, user: null, isHydrating: false };
    return;
  }

  state = { ...state, token, isHydrating: true };
  emit();

  try {
    const response = await apiFetch<{ user: User }>(API_ENDPOINTS.auth.me, {
      token,
    });
    state = { ...state, user: response.user, isHydrating: false };
    writeActiveStoreId(response.user.store_ids[0] ?? null);
  } catch {
    // Token expirado o inv\u00e1lido \u2192 limpiar.
    state = { token: null, user: null, isHydrating: false };
    writeTokenToStorage(null);
    writeActiveStoreId(null);
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
    isAuthenticated: snapshot.token !== null,
    isHydrating: snapshot.isHydrating,
    login,
    logout,
    setSession,
    verifyTotpChallenge,
  };
}
