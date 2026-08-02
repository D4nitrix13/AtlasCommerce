import { useSyncExternalStore } from "react";
import type { LoginPayload, LoginResponse, User } from "../types/auth";
import { apiFetch } from "../lib/api";

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

const TOKEN_STORAGE_KEY = "auth.token";

type AuthState = {
  token: string | null;
  user: User | null;
  isHydrating: boolean;
};

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
const STORE_ID_STORAGE_KEY = "auth.store_id";

export function getActiveStoreId(): number | null {
  if (typeof window === "undefined") {
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
  if (typeof window === "undefined") {
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
  if (typeof window === "undefined") {
    return null;
  }

  return window.localStorage.getItem(TOKEN_STORAGE_KEY);
}

function writeTokenToStorage(token: string | null): void {
  if (typeof window === "undefined") {
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
 * Setea la sesión a partir de una respuesta de auth (login o register).
 *
 * Usado por el flujo de register: el endpoint /auth/register ya
 * devuelve { token, user }, no necesitamos hacer un segundo POST
 * a /auth/login para obtener el token. Esta función lo guarda
 * directamente en el store + localStorage.
 */
export function setSession(response: LoginResponse): void {
  state = { ...state, token: response.token, user: response.user };
  writeTokenToStorage(response.token);
  writeActiveStoreId(response.user.store_ids[0] ?? null);
  emit();
}

async function login(payload: LoginPayload): Promise<LoginResponse> {
  const response = await apiFetch<LoginResponse>("/auth/login", {
    method: "POST",
    body: payload,
  });

  state = { ...state, token: response.token, user: response.user };
  writeTokenToStorage(response.token);
  writeActiveStoreId(response.user.store_ids[0] ?? null);
  emit();

  return response;
}

async function logout(): Promise<void> {
  try {
    if (state.token !== null) {
      await apiFetch("/auth/logout", {
        method: "POST",
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
  const token = state.token;

  if (token === null) {
    return;
  }

  state = { ...state, isHydrating: true };
  emit();

  

  try {
    const response = await apiFetch<{ user: User }>("/auth/me", { token });
    state = { ...state, user: response.user, isHydrating: false };
    writeActiveStoreId(response.user.store_ids[0] ?? null);
  } catch {
    // Token expirado o inválido → limpiar.
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
  subscribe,
  getSnapshot,
};

export function useAuth(): {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isHydrating: boolean;
  login: (payload: LoginPayload) => Promise<LoginResponse>;
  logout: () => Promise<void>;
  setSession: typeof setSession;
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
  };
}