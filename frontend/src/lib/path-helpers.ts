import { DEFAULT_LOCALE, SUPPORTED_LOCALES, type Locale } from '../i18n/config';

/**
 * Helpers de path para rutas con prefijo de locale.
 *
 * Astro i18n config (`astro.config.mjs:46-53`) setea
 * `prefixDefaultLocale: true` con `locales: ["es", "en"]`, así que
 * TODA ruta pública tiene la forma `/<locale>/...`. AuthGuard y los
 * redirects post-login necesitan leer/escribir el prefijo de locale
 * sin asumir que existe (fallback a DEFAULT_LOCALE).
 */

/**
 * Set de paths admin/auth CANONICALS (sin prefijo de locale ni `/admin/`).
 *
 * Si un usuario no autenticado llega a cualquiera de estos paths,
 * AuthGuard NO redirige. Cualquier otra ruta `/auth/...` o `/admin/...`
 * sí dispara redirect al login con `?redirect=`.
 *
 * Histórico: antes vivían como `/admin/auth/*` (subfolder mirror del URL).
 * Como los archivos Astro viven en `src/pages/[locale]/auth/` (sin
 * `admin/`) y el middleware redirige el path legacy `/admin/auth/*` →
 * `/auth/*` cuando el host es `admin.*`, el set de paths públicos
 * ahora son estos. Ver `LEGACY_ADMIN_AUTH_PATHS` abajo para los paths
 * legacy (usados solo por el middleware para redirigir el tráfico viejo).
 */
export const PUBLIC_ADMIN_PATHS: ReadonlySet<string> = new Set<string>([
  '/auth/login',
  '/auth/forgot-password',
  '/auth/reset-password',
  '/auth/reset-success',
  '/auth/2fa',
]);

/**
 * Set de paths admin/auth LEGACY (con prefijo `/admin/`).
 *
 * Usado SOLO por el middleware para redirigir el tráfico viejo cuando
 * un usuario llega con un link que aún tiene el prefijo `/admin/`.
 * El frontend NUNCA debe generar links a estos paths — el path canónico
 * es `/auth/*` (ver `PUBLIC_ADMIN_PATHS` arriba).
 */
export const LEGACY_ADMIN_AUTH_PATHS: ReadonlySet<string> = new Set<string>([
  '/admin/auth/login',
  '/admin/auth/forgot-password',
  '/admin/auth/reset-password',
  '/admin/auth/reset-success',
  '/admin/auth/2fa',
]);

/**
 * Extrae el locale del path `/<locale>/...`.
 *
 *   stripLocaleFromPath("/es/admin/dashboard")
 *     → { locale: "es", barePath: "/admin/dashboard" }
 *   stripLocaleFromPath("/es/auth/login")
 *     → { locale: "es", barePath: "/auth/login" }
 *   stripLocaleFromPath("/auth/login")
 *     → { locale: "es", barePath: "/auth/login" }   (fallback)
 *   stripLocaleFromPath("/")
 *     → { locale: "es", barePath: "/" }                  (fallback)
 */
export function stripLocaleFromPath(pathname: string): {
  locale: Locale;
  barePath: string;
} {
  if (pathname === '/' || pathname === '') {
    return { locale: DEFAULT_LOCALE, barePath: '/' };
  }

  const match = pathname.match(/^\/(es|en)(\/|$)/);
  if (match === null) {
    return { locale: DEFAULT_LOCALE, barePath: pathname };
  }

  const candidate = match[1];
  if (candidate === undefined || !(SUPPORTED_LOCALES as readonly string[]).includes(candidate)) {
    return { locale: DEFAULT_LOCALE, barePath: pathname };
  }

  const barePath = pathname.replace(/^\/(es|en)/, '') || '/';
  return { locale: candidate as Locale, barePath };
}

/**
 * Devuelve el locale activo en el pathname actual (window.location.pathname),
 * con fallback a DEFAULT_LOCALE.
 */
export function getLocaleFromPath(pathname: string): Locale {
  return stripLocaleFromPath(pathname).locale;
}

/**
 * Construye un path absoluto con el locale prefijado.
 *
 *   withLocale("/admin/dashboard", "es") → "/es/admin/dashboard"
 *   withLocale("/admin/dashboard", "en") → "/en/admin/dashboard"
 *   withLocale("/", "es")                → "/es"
 */
export function withLocale(barePath: string, locale: Locale): string {
  if (barePath === '/' || barePath === '') {
    return `/${locale}`;
  }

  const normalized = barePath.startsWith('/') ? barePath : `/${barePath}`;
  return `/${locale}${normalized}`;
}

/**
 * Determina si un barePath (locale ya removido) es una ruta admin/auth
 * pública. AuthGuard usa esto para no redirigir al login a usuarios
 * no autenticados que están siguiendo un email link o viendo 2FA.
 */
export function isPublicAdminPath(barePath: string): boolean {
  return PUBLIC_ADMIN_PATHS.has(barePath);
}
