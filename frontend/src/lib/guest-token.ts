/**
 * guest-token — identidad del visitante anónimo para "Visto recientemente".
 *
 * El backend persiste el historial por usuario (sesión Sanctum) o por
 * `guest_token` cuando no hay sesión. La cookie `rv_guest` es propiedad
 * de esta capa (Astro): UUID v4 opaco, ~1 año de vida, `SameSite=Lax`,
 * `path=/`. El SSR la lee de `Astro.cookies`, la crea si falta y la
 * manda como `guest_token` en los calls a
 * `POST/GET /api/v1/storefront/recently-viewed`.
 */

export const GUEST_TOKEN_COOKIE = 'rv_guest';

/** ~1 año — el historial es permanente (sin reset), la cookie lo acompaña. */
export const GUEST_TOKEN_MAX_AGE_SECONDS = 365 * 24 * 60 * 60;

/**
 * Subset estructural de `AstroCookies` que este helper necesita. Tipar
 * el jar por composición (y no importar el type de Astro) permite
 * testear la lógica sin el runtime de Astro.
 */
export interface GuestTokenCookies {
  get(name: string): { value: string } | undefined;
  set(name: string, value: string, options: GuestTokenCookieOptions): void;
}

export interface GuestTokenCookieOptions {
  maxAge: number;
  sameSite: 'lax';
  path: string;
}

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/** Genera el token huésped: UUID v4 opaco (no interpretable por el backend). */
export function createGuestToken(): string {
  return crypto.randomUUID();
}

function isValidGuestToken(value: string | undefined): value is string {
  return value !== undefined && UUID_V4.test(value);
}

/**
 * Resuelve el token del request: reutiliza la cookie si trae un UUID v4
 * válido; si falta o está corrupta, genera uno nuevo y lo setea.
 */
export function resolveGuestToken(cookies: GuestTokenCookies): string {
  const existing = cookies.get(GUEST_TOKEN_COOKIE)?.value;
  if (isValidGuestToken(existing)) {
    return existing;
  }

  const token = createGuestToken();
  cookies.set(GUEST_TOKEN_COOKIE, token, {
    maxAge: GUEST_TOKEN_MAX_AGE_SECONDS,
    sameSite: 'lax',
    path: '/',
  });
  return token;
}
