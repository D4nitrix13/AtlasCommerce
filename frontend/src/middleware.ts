import { defineMiddleware } from 'astro:middleware';
import {
  DEFAULT_LOCALE,
  LOCALE_FLAGS,
  LOCALE_LABELS,
  STORAGE_KEY,
  SUPPORTED_LOCALES,
  type Locale,
} from './i18n/config';
import { LEGACY_ADMIN_AUTH_PATHS, stripLocaleFromPath } from './lib/path-helpers';

/**
 * Middleware de Astro para ruteo de locale + subdominio admin.
 *
 * Reglas:
 *  - Si el host empieza con `admin.`, activamos el contexto admin y
 *    reescribimos paths limpios (`/login`, `/dashboard`) a paths internos
 *    (`/es/admin/auth/login`, `/es/admin/dashboard`). Así, las URLs
 *    externas son limpias pero el código interno mantiene `/[locale]/admin/`.
 *  - Para URLs storefront, se exige prefijo `/es/` o `/en/`. Si no lo
 *    tiene, redirige a `/es/...` (o al locale guardado en cookie).
 *  - El login admin **requiere** locale prefijado en la URL externa
 *    (`admin.kitsune.com/es/admin/auth/login`). Si la URL no tiene
 *    locale, el middleware hace REDIRECT (302) a la forma canónica.
 *    Convention: la locale siempre va inmediatamente después del dominio.
 *    Esto simplifica el LanguageSwitcher (siempre ve URLs canónicas)
 *    y elimina ambigüedad con URLs tipo `/es/auth/login` (404) vs
 *    `/es/admin/auth/login` (200).
 *
 * Single source of truth: `SUPPORTED_LOCALES`.
 */

interface TenantContext {
  isAdmin: boolean;
  tenantSlug: string | null;
}

interface LoginTheme {
  bg: string;
  surface: string;
  surfaceHover: string;
  border: string;
  borderStrong: string;
  text: string;
  textMuted: string;
  textSubtle: string;
  inputBg: string;
  inputBgFocus: string;
  danger: string;
  dangerText: string;
  dangerBg: string;
  divider: string;
}

interface Theme {
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

const DEFAULT_THEME: Theme = {
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
 * Resuelve la URL interna del backend para SSR.
 *
 * Server-side fetch desde el middleware de Astro usa `INTERNAL_API_URL`
 * (en Docker network es `http://app:8000/api`). Si no esta seteada,
 * cae a `PUBLIC_API_URL` o a `http://127.0.0.1:8000/api` por default.
 */
function resolveInternalApiUrl(): string {
  const fromImportMeta = (import.meta.env as Record<string, string | undefined>).INTERNAL_API_URL;
  if (typeof fromImportMeta === 'string' && fromImportMeta !== '') {
    return fromImportMeta.replace(/\/$/, '');
  }

  const fromPublic = (import.meta.env as Record<string, string | undefined>).PUBLIC_API_URL;
  if (typeof fromPublic === 'string' && fromPublic !== '') {
    return fromPublic.replace(/\/$/, '');
  }

  return 'http://127.0.0.1:8000/api';
}

/**
 * Fetch server-side del theme del tenant actual.
 *
 * Llama a `GET {INTERNAL_API_URL}/storefront/theme` pasando el host
 * para que el backend resuelva el tenant via `store.domain` middleware.
 *
 * Si falla (timeout, 404, network), devuelve `DEFAULT_THEME` para
 * que la pagina no se rompa.
 */
async function fetchThemeForHost(host: string): Promise<Theme> {
  const baseUrl = resolveInternalApiUrl();
  const url = `${baseUrl}/storefront/theme`;

  try {
    const response = await fetch(url, {
      headers: { Host: host, Accept: 'application/json' },
      signal: AbortSignal.timeout(1_000),
    });
    if (!response.ok) {
      return DEFAULT_THEME;
    }
    const body = (await response.json()) as { data?: Partial<Theme> };
    return {
      short_name: body.data?.short_name ?? null,
      logo_path: body.data?.logo_path ?? null,
      login_logo_path: body.data?.login_logo_path ?? null,
      favicon_path: body.data?.favicon_path ?? null,
      accent_color: body.data?.accent_color ?? null,
      accent_hover_color: body.data?.accent_hover_color ?? null,
      primary_color: body.data?.primary_color ?? null,
      support_email: body.data?.support_email ?? null,
      phone: body.data?.phone ?? null,
      whatsapp: body.data?.whatsapp ?? null,
      login_theme: body.data?.login_theme ?? null,
      tokens: body.data?.tokens ?? {},
    };
  } catch {
    return DEFAULT_THEME;
  }
}

const ADMIN_HOST_PATTERNS = [
  /^admin\.(localhost|127\.0\.0\.1)(?::\d+)?$/i,
  /^admin\.([a-z0-9-]+)\.test$/i,
  // Captura solo el primer label después de `admin.`.
  //   admin.kitsune.com           -> ["kitsune"]
  //   admin.panda-estampados.com  -> ["panda-estampados"]
  /^admin\.([a-z0-9-]+)(?:\.[a-z0-9.-]+)?$/i,
];

function detectTenantFromHost(host: string): TenantContext {
  const normalizedHost = host.split(':')[0] ?? '';

  for (const pattern of ADMIN_HOST_PATTERNS) {
    const match = normalizedHost.match(pattern);
    if (match !== null) {
      return {
        isAdmin: true,
        tenantSlug: match[1] ?? null,
      };
    }
  }

  // Host normal (no admin) → storefront.
  const parts = normalizedHost.split('.');
  const firstLabel = parts[0] ?? '';
  const tenantSlug =
    firstLabel !== '' && firstLabel !== 'www' && firstLabel !== 'localhost' ? firstLabel : null;

  return {
    isAdmin: false,
    tenantSlug,
  };
}

function detectLocalePrefix(pathname: string): Locale | null {
  const match = pathname.match(/^\/(es|en)(\/|$)/);

  if (match === null) {
    return null;
  }

  const candidate = match[1];

  return (SUPPORTED_LOCALES as readonly string[]).includes(candidate ?? '')
    ? (candidate as Locale)
    : null;
}

function pickDefaultLocale(request: Request): Locale {
  const cookieHeader = request.headers.get('cookie') ?? '';
  const match = cookieHeader.match(/(?:^|;\s*)app\.locale=([^;]+)/);

  if (match !== null) {
    const cookieValue = match[1];
    if (
      cookieValue !== undefined &&
      (SUPPORTED_LOCALES as readonly string[]).includes(cookieValue)
    ) {
      return cookieValue as Locale;
    }
  }

  const acceptLanguage = request.headers.get('accept-language') ?? '';
  const preferred = acceptLanguage
    .split(',')
    .map((entry) => entry.split(';')[0]?.split('-')[0]?.toLowerCase() ?? '')
    .find((tag) => (SUPPORTED_LOCALES as readonly string[]).includes(tag));

  if (preferred !== undefined && preferred !== '') {
    return preferred as Locale;
  }

  return DEFAULT_LOCALE;
}

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

function appendLocaleCookie(response: Response, locale: Locale): void {
  const value = `${STORAGE_KEY}=${locale}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax`;
  response.headers.append('set-cookie', value);
}

/**
 * Mapea paths externos limpios (sin locale ni `/admin/`) a paths internos
 * con prefijo `/[locale]/...`.
 *
 * El usuario ve `admin.kitsune.com/login`. Astro necesita apuntar a
 * `/es/account/login` (page real en `src/pages/[locale]/account/`).
 *
 * Solo se aplica cuando `isAdmin = true` y la URL no tiene ya prefijo
 * de locale ni está en assets.
 *
 * Historia: las páginas auth vivían en `src/pages/[locale]/auth/`
 * (path interno `/[locale]/auth/...`). Después de este refactor viven
 * en `src/pages/[locale]/account/` — el prefijo `/account/` agrupa
 * toda la gestión de la cuenta (login, signup, profile, etc.) y el
 * subdomain `admin.` ya marca el contexto admin, sin duplicar namespace.
 */
function mapAdminPathToInternal(pathname: string, locale: Locale): string {
  // Ya tiene prefijo de locale → no tocar.
  if (detectLocalePrefix(pathname) !== null) {
    return pathname;
  }

  const cleanPath = pathname === '/' ? '' : pathname;

  // Mapeo explícito de paths limpios a paths relativos al segmento
  // raíz `/`. El result final es `/${locale}${mapped}`.
  //   `/login`     -> `/es/account/login` (page existe en src/pages/[locale]/account/login.astro)
  //   `/dashboard` -> `/es/dashboard`
  // Cualquier path no listado pasa tal cual (será 404 si no existe).
  const pathMap: Record<string, string> = {
    '/login': '/account/login',
    '/dashboard': '/dashboard',
    '/users': '/users',
    '/sales': '/sales',
    '/purchases': '/purchases',
    '/inventory': '/inventory',
    '/catalog': '/catalog',
    '/customers': '/customers',
    '/marketing': '/marketing',
    '/shipping': '/shipping',
    '/support': '/support',
    '/settings': '/settings',
    '/reports': '/reports',
    '/system': '/system',
    '/notifications': '/system/notifications',
    '/sales/orders': '/sales/orders',
    '/sales/invoices': '/sales/invoices',
  };

  const mapped = pathMap[cleanPath] ?? cleanPath;
  return mapped === '' ? `/${locale}/admin` : `/${locale}${mapped}`;
}

const PUBLIC_PATHS = [
  '/api/',
  '/_astro/',
  '/_actions/',
  '/_image',
  '/build/',
  '/favicon.ico',
  '/robots.txt',
];

function isPublicPath(pathname: string): boolean {
  return PUBLIC_PATHS.some((prefix) => pathname.startsWith(prefix));
}

function isStaticAsset(pathname: string): boolean {
  return pathname.startsWith('/_') || pathname.startsWith('/build/');
}

export const onRequest = defineMiddleware(async (context, next) => {
  const url = new URL(context.request.url);
  const pathname = url.pathname;
  const host = context.request.headers.get('host') ?? '';

  const tenant = detectTenantFromHost(host);

  // Exponer tenant context a las páginas.
  context.locals.tenant = tenant;

  // Hosts admin: rewrite de paths limpios a paths internos.
  if (tenant.isAdmin) {
    if (isStaticAsset(pathname)) {
      return next();
    }

    // Fetch server-side del theme del tenant actual.
    context.locals.theme = await fetchThemeForHost(host);

    const locale = pickDefaultLocale(context.request);
    const localeFromUrl = detectLocalePrefix(pathname);

    if (localeFromUrl === null) {
      // URL no tiene locale → REDIRECT (302) a la forma canónica
      // con prefijo /[locale]/admin/.... Convention: la locale va
      // inmediatamente después del dominio para que:
      //   - El LanguageSwitcher siempre vea URLs canónicas
      //     (`/[locale]/admin/...`) y genere URLs válidas al cambiar.
      //   - Compartir/bookmarkear una URL siempre incluya la locale
      //     (evita que el receptor vea otro idioma por Accept-Language).
      //   - Sea imposible terminar en URLs inválidas como `/en/admin/auth/login`
      //     (que 404 porque el page real vive en `/en/account/login`).
      // Preservar query string (ej: ?tab=register en /login).
      const canonicalPath = mapAdminPathToInternal(pathname, locale) + url.search;
      context.locals.locale = locale;
      const redirectResponse = context.redirect(canonicalPath, 302);
      appendLocaleCookie(redirectResponse, locale);
      return redirectResponse;
    }

    // URL tiene locale → dejar que Astro rutee, solo setear locale.
    context.locals.locale = localeFromUrl;

    // Redirect legacy `/<locale>/admin/auth/*` → `/<locale>/account/*`.
    // Después del refactor los archivos viven en `src/pages/[locale]/account/`
    // (no en `admin/auth/`), pero links viejos pueden apuntar al path
    // con `/admin/`. Redirigimos 301 al canónico para no 404.
    const { barePath } = stripLocaleFromPath(pathname);
    if (LEGACY_ADMIN_AUTH_PATHS.has(barePath)) {
      const canonical = barePath.replace(/^\/admin\/auth/, '/account');
      const newPath = `/${localeFromUrl}${canonical === '/' ? '' : canonical}` + url.search;
      return context.redirect(newPath, 301);
    }

    const response = await next();
    appendLocaleCookie(response, localeFromUrl);
    return response;
  }

  // Host storefront (no admin): behavior original.
  const localeFromUrl = detectLocalePrefix(pathname);

  if (localeFromUrl !== null) {
    context.locals.locale = localeFromUrl;
    // Fetch del theme del tenant para storefront.
    context.locals.theme = await fetchThemeForHost(host);
    const response = await next();
    appendLocaleCookie(response, localeFromUrl);
    return response;
  }

  if (isPublicPath(pathname)) {
    return next();
  }

  const targetLocale = pickDefaultLocale(context.request);
  const redirectTo = `/${targetLocale}${pathname === '/' ? '' : pathname}${url.search ?? ''}`;

  const redirectResponse = context.redirect(redirectTo, 302);
  appendLocaleCookie(redirectResponse, targetLocale);

  return redirectResponse;
});

// Tipos para `Astro.locals`.
declare global {
  namespace App {
    interface Locals {
      locale: Locale;
      tenant: TenantContext;
      theme: Theme;
    }
  }
}

// Re-exports para tooling que necesita tipos de i18n.
export { LOCALE_FLAGS, LOCALE_LABELS };
