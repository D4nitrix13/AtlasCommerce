import { DEFAULT_BRAND, type LoginTheme, type StoreBrand } from '../config/stores';

/**
 * Theme visual de una tienda tal como lo consume el frontend.
 *
 * Esta es la versión TypeScript del JSON que devuelve el endpoint
 * `GET /api/storefront/theme` (ver `StoreThemeResource` en el backend).
 *
 * El middleware de Astro (`src/middleware.ts`) inyecta una instancia
 * de este tipo en `Astro.locals.theme`.
 */
export interface Theme {
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
}

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
 * Reescribe paths legacy (`/images/tenants/...`) al formato del refactor
 * de Fase 6 (`/api/storefront/assets/tenants/...`). El backend de La Voz
 * tiene datos anteriores al refactor con paths `/images/tenants/...` que
 * el backend sirve en 404. Reescribimos acá para que el backend sirva el
 * asset via su AssetController.
 */
function rewriteLegacyAssetPath(path: string | null, tenantSlug: string | null): string | null {
  if (path === null) {
    return null;
  }
  if (path.startsWith('/images/tenants/')) {
    return path.replace(/^\/images\/tenants\//, `/api/storefront/assets/tenants/${tenantSlug ?? ''}/`);
  }
  if (path.startsWith('/images/')) {
    return path.replace(/^\/images\//, '/api/storefront/assets/');
  }
  return path;
}

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
 * @param  tenantSlug  Slug del tenant (para rewrite de paths legacy).
 */
export function themeToBrand(
  theme: Theme,
  fallback: StoreBrand,
  tenantSlug: string | null = null,
): StoreBrand {
  return {
    ...fallback,
    shortName: theme.short_name ?? fallback.shortName,
    name: theme.short_name ?? fallback.name,
    logoPath: rewriteLegacyAssetPath(theme.logo_path, tenantSlug) ?? fallback.logoPath,
    loginLogoPath: rewriteLegacyAssetPath(theme.login_logo_path, tenantSlug) ?? fallback.loginLogoPath,
    accentColor: theme.accent_color ?? fallback.accentColor,
    accentHoverColor: theme.accent_hover_color ?? fallback.accentHoverColor,
    supportEmail: theme.support_email ?? fallback.supportEmail,
    loginTheme: theme.login_theme ?? fallback.loginTheme,
  };
}

export { DEFAULT_BRAND, rewriteLegacyAssetPath };
