import { useSyncExternalStore } from 'react';
import { apiFetch } from '../lib/api';
import { API_ENDPOINTS } from '../lib/endpoints';
import type { Customer, CustomerType } from '../types/customers';
import type { User } from '../types/auth';

/**
 * Store de autenticación para CUSTOMERS del storefront (CA.4).
 *
 * Espejo de `stores/auth.ts` (que es para User admin/staff) pero con
 * shape distinto: además del User persistimos el Customer profile
 * (CA.3 agregó el campo `customer` en la response del login y
 * register). El frontend storefront necesita ambos para hidratar el
 * Account dashboard (F2.2 task-oriented spec).
 *
 * Por qué store separado y no reuso de auth.ts:
 *  - Distintos concerns: admin/staff y customer son dominios
 *    distintos. Mezclar en un solo store forzaría tipos unión y
 *    type guards innecesarios.
 *  - Distinto lifecycle: el admin logout va a /auth/login (panel
 *    admin). El customer logout va a / (homepage del storefront).
 *  - Distinto target: el admin auth usa /api/v1/auth/me, el
 *    customer auth usa el mismo /me pero hidrata Customer extra.
 *
 * El token se guarda en cookie httpOnly via Sanctum stateful (CR2).
 * El frontend nunca lo lee — el browser lo manda automáticamente
 * en cada request al mismo origin. La única razón de mantener
 * `token` en el state es para compat con callers (ver NOTA en
 * stores/auth.ts sobre CR2).
 */
interface CustomerAuthState {
  /**
   * @deprecated CA.4 — siempre null (token en cookie httpOnly). Igual
   * que en `stores/auth.ts`. Mantenido por compat con callers.
   */
  token: string | null;
  user: User | null;
  customer: Customer | null;
  isHydrating: boolean;
}

const initialState: CustomerAuthState = {
  token: null,
  user: null,
  customer: null,
  // CA.4 — arrancamos con isHydrating=true para que los componentes
  // que dependen del customer (AccountAuthGuard, AccountDashboard)
  // NO redirijan a /account/login antes de que hydrate() haya llamado
  // a /me. Se setea a false al final de hydrate().
  isHydrating: true,
};

let state: CustomerAuthState = initialState;
const listeners = new Set<() => void>();

function emit(): void {
  for (const listener of listeners) {
    listener();
  }
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

function getSnapshot(): CustomerAuthState {
  return state;
}

/**
 * CA.4 — `isAdminUser` reusado desde `stores/auth.ts` pero aplicado al
 * user del customer store. Si el user del customer tiene role distinto
 * a 'customer' (data inconsistency), el AccountAuthGuard debe
 * rechazar (defense in depth).
 */
export function isCustomerUser(user: User | null): boolean {
  if (user === null) {
    return false;
  }
  return user.role?.slug === 'customer';
}

interface RegisterCustomerPayload {
  email: string;
  password: string;
  password_confirmation: string;
  first_name: string;
  last_name: string;
  phone?: string;
  customer_type?: CustomerType;
}

interface CustomerAuthResponse {
  token: string;
  user: User;
  customer: Customer;
}

interface CustomerLoginResponse {
  token: string;
  user: User;
  customer: Customer | null;
}

/**
 * CA.4 — hidrata el customer desde /me. Si la respuesta no incluye
 * customer (data inconsistency — el user es role='customer' pero no
 * tiene Customer profile), el campo queda null y el AccountAuthGuard
 * muestra el fallback "Cliente" en vez de tirar error.
 */
async function hydrate(): Promise<void> {
  state = { ...state, isHydrating: true };
  emit();

  try {
    const response = await apiFetch<CustomerLoginResponse>(API_ENDPOINTS.auth.me, {
      credentials: 'include',
    });
    state = {
      ...state,
      user: response.user,
      customer: response.customer,
      isHydrating: false,
    };
  } catch {
    // 401/403 → no hay sesión válida. Limpiamos el state.
    state = { ...initialState, isHydrating: false };
  }
  emit();
}

/**
 * CA.2 — registra un nuevo Customer en el storefront. Crea User
 * (role='customer') + Customer profile linked + emite Sanctum PAT.
 * El browser recibe el token via Set-Cookie de Sanctum stateful,
 * pero también devolvemos el token en la response para que el
 * frontend lo persista (compat con callers que esperan Bearer).
 */
async function register(payload: RegisterCustomerPayload): Promise<CustomerAuthResponse> {
  const response = await apiFetch<CustomerAuthResponse>(API_ENDPOINTS.auth.register, {
    method: 'POST',
    // Cast a Record<string, unknown>: la firma de RequestInit.body
    // acepta varios tipos (FormData, Blob, etc) y TypeScript no
    // puede inferir desde un interface custom. El runtime valida
    // que el payload es plain object y lo serializa a JSON.
    body: payload as unknown as Record<string, unknown>,
    credentials: 'include',
  });

  state = {
    ...state,
    user: response.user,
    customer: response.customer,
  };
  emit();

  return response;
}

/**
 * CA.3 — login de Customer. Usa el endpoint existente /api/v1/auth/login
 * (que ahora devuelve customer en la response si aplica). Mismo patrón
 * que register: Sanctum stateful setea la cookie, también devolvemos
 * el token en la response para compat.
 */
async function login(email: string, password: string): Promise<CustomerLoginResponse> {
  const response = await apiFetch<CustomerLoginResponse>(API_ENDPOINTS.auth.login, {
    method: 'POST',
    body: { email, password },
    credentials: 'include',
  });

  state = {
    ...state,
    user: response.user,
    customer: response.customer,
  };
  emit();

  return response;
}

/**
 * CA.4 — logout del Customer. El backend usa auth:sanctum (que acepta
 * sesión o token). Sanctum stateful envía la cookie automáticamente.
 * Limpiamos el state local siempre (incluso si el backend falla) para
 * que la UI no muestre datos stale.
 */
async function logout(): Promise<void> {
  try {
    await apiFetch(API_ENDPOINTS.auth.logout, {
      method: 'POST',
      credentials: 'include',
    });
  } catch {
    // Ignorar errores de logout (sesión expirada, network, etc).
    // El objetivo es limpiar el state local de todas formas.
  }

  state = { ...initialState, isHydrating: false };
  emit();
}

export const customerAuthStore = {
  hydrate,
  register,
  login,
  logout,
  subscribe,
  getSnapshot,
};

export function useCustomerAuth(): {
  user: User | null;
  customer: Customer | null;
  isHydrating: boolean;
  isAuthenticated: boolean;
  register: (payload: RegisterCustomerPayload) => Promise<CustomerAuthResponse>;
  login: (email: string, password: string) => Promise<CustomerLoginResponse>;
  logout: () => Promise<void>;
} {
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  return {
    user: snapshot.user,
    customer: snapshot.customer,
    isHydrating: snapshot.isHydrating,
    // CA.4 — un customer está autenticado si tiene user Y customer
    // populated. Si tiene solo user pero no customer (data
    // inconsistency), NO lo consideramos authenticated — el guard
    // mostrará el fallback.
    isAuthenticated: snapshot.user !== null && snapshot.customer !== null,
    register,
    login,
    logout,
  };
}
