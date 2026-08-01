import { apiFetch } from "./api";
import { getAuthToken } from "../stores/auth";

/**
 * HTTP client que inyecta automáticamente el Bearer token del store.
 *
 * Wrapper sobre `apiFetch` pensado para componentes que ya están
 * dentro del flujo autenticado. Para endpoints públicos (login),
 * usar `apiFetch` directo.
 *
 * Si no hay token, el request sigue (fallará con 401 en el backend
 * si el endpoint requiere auth, pero permite login sin token).
 */
export async function authedFetch<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getAuthToken();

  return apiFetch<T>(endpoint, {
    ...options,
    token,
  });
}

export { apiFetch };