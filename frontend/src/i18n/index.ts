import { useSyncExternalStore } from "react";
import en from "./locales/en";
import es from "./locales/es";
import {
  DEFAULT_LOCALE,
  LOCALE_LABELS,
  LOCALE_FLAGS,
  STORAGE_KEY,
  SUPPORTED_LOCALES,
  type Locale,
} from "./config";
import type { Translations } from "./types";

/**
 * Sistema i18n client-side.
 *
 * Detección de locale en orden de prioridad:
 *  1. URL pathname (`/es/...` o `/en/...`) — fuente de verdad (SSR-safe).
 *  2. Cookie `app.locale` — preferencia persistida por el cliente.
 *  3. localStorage `app.locale` — fallback legacy (pre-URL).
 *  4. `navigator.language`.
 *  5. DEFAULT_LOCALE.
 *
 * El locale activo se **deriva del URL en cada lectura**. El hook React
 * se re-renderiza cuando cambia `pathname` (via popstate + MutationObserver).
 *
 * `setLocale()` ya no cambia el estado local — debe **navegar** a la
 * nueva URL. Se mantiene la firma para compat con código existente,
 * pero emite warning si lo invocan sin navegar.
 */

type LocaleDictionary = Record<Locale, Translations>;

const dictionaries: LocaleDictionary = { es, en };

export function getLocale(): Locale {
  return detectFromUrl();
}

export function detectFromUrl(): Locale {
  if (typeof window === "undefined") {
    return DEFAULT_LOCALE;
  }

  // 1. URL pathname (cuando el middleware rewrite NO aplicó).
  const urlMatch = window.location.pathname.match(/^\/(es|en)(\/|$)/);
  if (urlMatch !== null) {
    const candidate = urlMatch[1];
    if (candidate !== undefined && (SUPPORTED_LOCALES as readonly string[]).includes(candidate)) {
      return candidate as Locale;
    }
  }

  // 2. Cookie `app.locale` (seteada por el middleware server-side).
  const cookieMatch = document.cookie.match(/(?:^|;\s*)app\.locale=([^;]+)/);
  if (cookieMatch !== null && cookieMatch[1] !== undefined) {
    const value = cookieMatch[1];
    if ((SUPPORTED_LOCALES as readonly string[]).includes(value)) {
      return value as Locale;
    }
  }

  // 3. localStorage legacy (compat con código pre-URL).
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored !== null && (SUPPORTED_LOCALES as readonly string[]).includes(stored)) {
      return stored as Locale;
    }
  } catch {
    // localStorage puede fallar en modo privado.
  }

  return DEFAULT_LOCALE;
}

/**
 * Construye una URL equivalente con el locale reemplazado.
 *
 * Ej: switchLocalePath("/es/admin/shipping", "en") → "/en/admin/shipping"
 *     switchLocalePath("/admin/shipping", "en")        → "/en/admin/shipping"
 *     switchLocalePath("/", "en")                       → "/en/"
 */
export function switchLocalePath(pathname: string, next: Locale): string {
  const replaced = pathname.replace(/^\/(es|en)/, `/${next}`);

  if (replaced === pathname) {
    return next === DEFAULT_LOCALE ? pathname : `/${next}${pathname === "/" ? "" : pathname}`;
  }

  return replaced;
}

export function setLocale(locale: Locale): void {
  if (typeof window === "undefined") {
    return;
  }

  // Persistir preferencia (cookie + localStorage).
  document.cookie = `${STORAGE_KEY}=${locale}; path=/; max-age=31536000; samesite=lax`;
  window.localStorage.setItem(STORAGE_KEY, locale);

  // Navegar a la URL equivalente con el nuevo locale.
  const newPath = switchLocalePath(window.location.pathname, locale);
  window.location.href = newPath + window.location.search;
}

function subscribe(listener: () => void): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  // Re-render cuando el pathname cambia (history navigation).
  window.addEventListener("popstate", listener);

  // Astro client-side router emite eventos custom; el más común es
  // 'astro:after-swap'. Lo escuchamos también.
  window.addEventListener("astro:after-swap", listener);

  // Fallback: MutationObserver sobre el <html> para detectar cambios
  // de URL via pushState (que no dispara popstate).
  let lastPath = window.location.pathname;
  const observer = new MutationObserver(() => {
    if (window.location.pathname !== lastPath) {
      lastPath = window.location.pathname;
      listener();
    }
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  return () => {
    window.removeEventListener("popstate", listener);
    window.removeEventListener("astro:after-swap", listener);
    observer.disconnect();
  };
}

function getSnapshot(): Locale {
  return detectFromUrl();
}

export function getDictionary(): Translations {
  return dictionaries[detectFromUrl()];
}

/**
 * Traduce una key con interpolación opcional.
 */
export function t(
  key: keyof Translations,
  vars?: Record<string, string | number>,
): string {
  const locale = detectFromUrl();
  const value = dictionaries[locale][key];

  if (typeof value !== "string") {
    if (typeof window !== "undefined" && import.meta.env.DEV === true) {
      console.warn(`[i18n] Missing translation for key "${String(key)}" in locale "${locale}"`);
    }

    return String(key);
  }

  if (vars === undefined) {
    return value;
  }

  return value.replace(/\{(\w+)\}/g, (match, name: string) => {
    const replacement = vars[name];

    return replacement === undefined ? match : String(replacement);
  });
}

export { LOCALE_LABELS, LOCALE_FLAGS, SUPPORTED_LOCALES };
export type { Locale };

/**
 * Hook React: `t()` bound al locale actual + setter para cambiarlo.
 */
export function useTranslations(): {
  t: typeof t;
  locale: Locale;
  setLocale: typeof setLocale;
  switchLocalePath: typeof switchLocalePath;
} {
  const locale = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  return { t, locale, setLocale, switchLocalePath };
}