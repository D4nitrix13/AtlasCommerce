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
 * Set de paths admin/auth p\u00fablicos (sin prefijo de locale).
 *
 * Si un usuario no autenticado llega a cualquiera de estos paths,
 * AuthGuard NO redirige. Cualquier otra ruta `/admin/...` (incluido
 * `/admin/dashboard`) s\u00ed dispara redirect al login con `?redirect=`.
 *
 * Mantener sincronizado con las p\u00e1ginas Astro bajo
 * `src/pages/[locale]/admin/auth/`.
 */
export const PUBLIC_ADMIN_PATHS: ReadonlySet<string> = new Set<string>([
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
 *     \u2192 { locale: "es", barePath: "/admin/dashboard" }
 *   stripLocaleFromPath("/en/admin/auth/login")
 *     \u2192 { locale: "en", barePath: "/admin/auth/login" }
 *   stripLocaleFromPath("/admin/dashboard")
 *     \u2192 { locale: "es", barePath: "/admin/dashboard" }   (fallback)
 *   stripLocaleFromPath("/")
 *     \u2192 { locale: "es", barePath: "/" }                  (fallback)
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
 *   withLocale("/admin/dashboard", "es") \u2192 "/es/admin/dashboard"
 *   withLocale("/admin/dashboard", "en") \u2192 "/en/admin/dashboard"
 *   withLocale("/", "es")                \u2192 "/es"
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
 * p\u00fablica. AuthGuard usa esto para no redirigir al login a usuarios
 * no autenticados que est\u00e1n siguiendo un email link o viendo 2FA.
 */
export function isPublicAdminPath(barePath: string): boolean {
  return PUBLIC_ADMIN_PATHS.has(barePath);
}
