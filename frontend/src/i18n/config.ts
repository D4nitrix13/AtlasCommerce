/**
 * Configuración del sistema i18n.
 *
 * Locales soportados + locale por default. La detección runtime vive
 * en `index.ts` (localStorage → navigator.language → default).
 */

export const SUPPORTED_LOCALES = ["es", "en"] as const;
export type Locale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: Locale = "es";

export const LOCALE_LABELS: Record<Locale, string> = {
  es: "Español",
  en: "English",
};

export const LOCALE_FLAGS: Record<Locale, string> = {
  es: "🇪🇸",
  en: "🇺🇸",
};

export const STORAGE_KEY = "app.locale";