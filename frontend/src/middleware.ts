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
 * Middleware de Astro para ruteo de locale en URL.
 *
 * Reglas:
 *  - Toda URL admin/storefront/account DEBE tener prefijo `/es/` o `/en/`.
 *  - Si no tiene prefijo, redirige a `/es/...` (o `/en/` si el cliente
 *    tiene preferencia guardada en cookie `app.locale`).
 *  - Si tiene prefijo válido, hace `Astro.rewrite()` a la ruta interna
 *    (sin prefijo) y expone `Astro.locals.locale` para que las páginas
 *    lo lean.
 *  - El login page NO requiere locale (es pública). La acepta igual
 *    por consistencia pero no falla.
 *
 * Single source of truth: `SUPPORTED_LOCALES`.
 */

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

function stripLocalePrefix(pathname: string, locale: Locale): string {
  const stripped = pathname.replace(new RegExp(`^/${locale}`), "");

  return stripped === "" ? "/" : stripped;
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

export const onRequest = defineMiddleware(async (context, next) => {
  const pathname = new URL(context.request.url).pathname;
  const localeFromUrl = detectLocalePrefix(pathname);

  // Caso 1: URL con prefijo válido → dejamos que Astro rutee por
  // su i18n config (páginas viven en `[locale]/admin/...`). Solo
  // seteamos `Astro.locals.locale` y la cookie.
  if (localeFromUrl !== null) {
    context.locals.locale = localeFromUrl;
    const response = await next();
    appendLocaleCookie(response, localeFromUrl);
    return response;
  }

  // Caso 2: URL sin prefijo en rutas privadas → redirect.
  const isPublicPath =
    pathname.startsWith("/api/") ||
    pathname.startsWith("/_astro/") ||
    pathname.startsWith("/_actions/") ||
    pathname.startsWith("/_image") ||
    pathname === "/_image" ||
    pathname.startsWith("/build/") ||
    pathname === "/favicon.ico" ||
    pathname === "/robots.txt";

  if (isPublicPath) {
    return next();
  }

  const targetLocale = pickDefaultLocale(context.request);
  const redirectTo = `/${targetLocale}${pathname === "/" ? "" : pathname}${context.url.search ?? ""}`;

  const redirectResponse = context.redirect(redirectTo, 302);
  appendLocaleCookie(redirectResponse, targetLocale);

  return redirectResponse;
});

// Tipos para `Astro.locals.locale`.
declare global {
  namespace App {
    interface Locals {
      locale: Locale;
    }
  }
}
