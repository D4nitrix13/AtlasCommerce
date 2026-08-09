import { DEFAULT_BRAND, type LoginTheme, type StoreBrand } from "../config/stores";

/**
 * Theme visual de una tienda tal como lo consume el frontend.
 *
 * Esta es la versión TypeScript del JSON que devuelve el endpoint
 * `GET /api/storefront/theme` (ver `StoreThemeResource` en el backend).
 *
 * El middleware de Astro (`src/middleware.ts`) inyecta una instancia
 * de este tipo en `Astro.locals.theme`.
 */
export type Theme = {
  short_name: string | null;
  logo_path: string | null;
  login_logo_path: string | null;
  favicon_path: string | null;
  accent_color: string | null;
  accent_hover_color: string | null;
  primary_color: string | null;
  support_email: string | null;
  phone: string | null;
  whatsapp: string | null;
  login_theme: LoginTheme | null;
  tokens: Record<string, unknown>;
};

/**
 * Theme por defecto cuando el backend no devuelve uno o falla el fetch.
 *
 * Todos los campos son `null` excepto `tokens` (objeto vacio). El
 * frontend puede usar estos valores como fallback sin romperse.
 */
export const DEFAULT_THEME: Theme = {
  short_name: null,
  logo_path: null,
  login_logo_path: null,
  favicon_path: null,
  accent_color: null,
  accent_hover_color: null,
  primary_color: null,
  support_email: null,
  phone: null,
  whatsapp: null,
  login_theme: null,
  tokens: {},
};

/**
 * Combina un theme dinámico (de la API) con un brand estático (de
 * `STORE_BRANDS`) para producir el `StoreBrand` que el frontend usa.
 *
 * Prioridad:
 *   1. Valores del theme (si no son null).
 *   2. Valores del brand fallback.
 *
 * Esto permite que la configuración estática (`STORE_BRANDS`) defina
 * los defaults (logo, color, etc) y que el theme en DB los overridee
 * sin deploys.
 *
 * @param  theme  Theme devuelto por la API.
 * @param  fallback  Brand estático de `STORE_BRANDS`.
 */
export function themeToBrand(theme: Theme, fallback: StoreBrand): StoreBrand {
  return {
    ...fallback,
    shortName: theme.short_name ?? fallback.shortName,
    name: theme.short_name ?? fallback.name,
    logoPath: theme.logo_path ?? fallback.logoPath,
    loginLogoPath: theme.login_logo_path ?? fallback.loginLogoPath,
    accentColor: theme.accent_color ?? fallback.accentColor,
    accentHoverColor: theme.accent_hover_color ?? fallback.accentHoverColor,
    supportEmail: theme.support_email ?? fallback.supportEmail,
    loginTheme: theme.login_theme ?? fallback.loginTheme,
  };
}

export { DEFAULT_BRAND };
