import { defineMiddleware } from "astro:middleware";
import {
  DEFAULT_LOCALE,
  LOCALE_FLAGS,
  LOCALE_LABELS,
  STORAGE_KEY,
  SUPPORTED_LOCALES,
  type Locale,
} from "./i18n/config";

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
 *  - El login admin no requiere locale prefijado en la URL externa
 *    (`admin.kitsune.com/login`).
 *
 * Single source of truth: `SUPPORTED_LOCALES`.
 */

interface TenantContext {
  isAdmin: boolean;
  tenantSlug: string | null;
}

const ADMIN_HOST_PATTERNS = [
  /^admin\.(localhost|127\.0\.0\.1)(?::\d+)?$/i,
  /^admin\.([a-z0-9-]+)\.test$/i,
  // Captura solo el primer label después de `admin.`.
  //   admin.kitsune.com             -> ["kitsune"]
  //   admin.pandas-estampados.com   -> ["pandas-estampados"]
  /^admin\.([a-z0-9-]+)(?:\.[a-z0-9.-]+)?$/i,
];

function detectTenantFromHost(host: string): TenantContext {
  const normalizedHost = host.split(":")[0] ?? "";

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
  const parts = normalizedHost.split(".");
  const firstLabel = parts[0] ?? "";
  const tenantSlug =
    firstLabel !== "" && firstLabel !== "www" && firstLabel !== "localhost"
      ? firstLabel
      : null;

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

  return (SUPPORTED_LOCALES as readonly string[]).includes(candidate ?? "")
    ? (candidate as Locale)
    : null;
}

function pickDefaultLocale(request: Request): Locale {
  const cookieHeader = request.headers.get("cookie") ?? "";
  const match = cookieHeader.match(/(?:^|;\s*)app\.locale=([^;]+)/);

  if (match !== null) {
    const cookieValue = match[1];
    if (cookieValue !== undefined && (SUPPORTED_LOCALES as readonly string[]).includes(cookieValue)) {
      return cookieValue as Locale;
    }
  }

  const acceptLanguage = request.headers.get("accept-language") ?? "";
  const preferred = acceptLanguage
    .split(",")
    .map((entry) => entry.split(";")[0]?.split("-")[0]?.toLowerCase() ?? "")
    .find((tag) => (SUPPORTED_LOCALES as readonly string[]).includes(tag));

  if (preferred !== undefined && preferred !== "") {
    return preferred as Locale;
  }

  return DEFAULT_LOCALE;
}

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

function appendLocaleCookie(response: Response, locale: Locale): void {
  const value = `${STORAGE_KEY}=${locale}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax`;
  response.headers.append("set-cookie", value);
}

/**
 * Mapea paths externos limpios (sin locale ni /admin/) a paths internos
 * con prefijo `/[locale]/admin/...`.
 *
 * El usuario ve `admin.kitsune.com/login`. Astro necesita apuntar a
 * `/es/admin/auth/login` (page real en `src/pages/[locale]/admin/...`).
 *
 * Solo se aplica cuando `isAdmin = true` y la URL no tiene ya prefijo
 * de locale ni está en assets.
 */
function mapAdminPathToInternal(pathname: string, locale: Locale): string {
  // Ya tiene prefijo de locale → no tocar.
  if (detectLocalePrefix(pathname) !== null) {
    return pathname;
  }

  const cleanPath = pathname === "/" ? "" : pathname;

  // Mapeo explícito de paths limpios a paths relativos al segmento
  // /admin/. El result final es `/${locale}/admin${mapped}`.
  //   `/login`     -> `/es/admin/auth/login` (page existe en src/pages/[locale]/admin/auth/login.astro)
  //   `/dashboard` -> `/es/admin/dashboard`
  // Cualquier path no listado pasa tal cual (será 404 si no existe).
  const pathMap: Record<string, string> = {
    "/login": "/auth/login",
    "/dashboard": "/dashboard",
    "/users": "/users",
    "/sales": "/sales",
    "/purchases": "/purchases",
    "/inventory": "/inventory",
    "/catalog": "/catalog",
    "/customers": "/customers",
    "/marketing": "/marketing",
    "/shipping": "/shipping",
    "/support": "/support",
    "/settings": "/settings",
    "/reports": "/reports",
    "/system": "/system",
    "/notifications": "/system/notifications",
    "/sales/orders": "/sales/orders",
    "/sales/invoices": "/sales/invoices",
  };

  const mapped = pathMap[cleanPath] ?? cleanPath;
  return mapped === "" ? `/${locale}/admin` : `/${locale}/admin${mapped}`;
}

const PUBLIC_PATHS = [
  "/api/",
  "/_astro/",
  "/_actions/",
  "/_image",
  "/build/",
  "/favicon.ico",
  "/robots.txt",
];

function isPublicPath(pathname: string): boolean {
  return PUBLIC_PATHS.some((prefix) => pathname.startsWith(prefix));
}

function isStaticAsset(pathname: string): boolean {
  return pathname.startsWith("/_") || pathname.startsWith("/build/");
}

export const onRequest = defineMiddleware(async (context, next) => {
  const url = new URL(context.request.url);
  const pathname = url.pathname;
  const host = context.request.headers.get("host") ?? "";

  const tenant = detectTenantFromHost(host);

  // Exponer tenant context a las páginas.
  context.locals.tenant = tenant;

  // Hosts admin: rewrite de paths limpios a paths internos.
  if (tenant.isAdmin) {
    if (isStaticAsset(pathname)) {
      return next();
    }

    const locale = pickDefaultLocale(context.request);
    const localeFromUrl = detectLocalePrefix(pathname);

    if (localeFromUrl === null) {
      // URL no tiene locale → rewrite a path interno con locale.
      const internalPath = mapAdminPathToInternal(pathname, locale);
      context.locals.locale = locale;
      const response = await context.rewrite(internalPath);
      appendLocaleCookie(response, locale);
      return response;
    }

    // URL tiene locale → dejar que Astro rutee, solo setear locale.
    context.locals.locale = localeFromUrl;
    const response = await next();
    appendLocaleCookie(response, localeFromUrl);
    return response;
  }

  // Host storefront (no admin): behavior original.
  const localeFromUrl = detectLocalePrefix(pathname);

  if (localeFromUrl !== null) {
    context.locals.locale = localeFromUrl;
    const response = await next();
    appendLocaleCookie(response, localeFromUrl);
    return response;
  }

  if (isPublicPath(pathname)) {
    return next();
  }

  const targetLocale = pickDefaultLocale(context.request);
  const redirectTo = `/${targetLocale}${pathname === "/" ? "" : pathname}${url.search ?? ""}`;

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
    }
  }
}

// Re-exports para tooling que necesita tipos de i18n.
export { LOCALE_FLAGS, LOCALE_LABELS };
