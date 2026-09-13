import en from './locales/en';
import es from './locales/es';
import { DEFAULT_LOCALE, SUPPORTED_LOCALES, type Locale } from './config';
import type { Translations } from './types';

/**
 * Helper server-side para páginas .astro.
 *
 * Astro middleware setea `Astro.locals.locale` (default: `es`). Esta
 * función devuelve el diccionario de traducciones correspondiente
 * para que las páginas Astro consuman strings sin volver a importar
 * los locales manualmente.
 *
 * Uso en un .astro:
 *   ---
 *   import { getServerDictionary } from "../i18n/server";
 *   const t = getServerDictionary(Astro.locals.locale);
 *   ---
 *   <h1>{t["admin.shipping.title"]}</h1>
 */
const dictionaries: Record<Locale, Translations> = { es, en };

export function getServerDictionary(locale: string | undefined): Translations {
  if (locale !== undefined && (SUPPORTED_LOCALES as readonly string[]).includes(locale)) {
    return dictionaries[locale as Locale];
  }

  return dictionaries[DEFAULT_LOCALE];
}

export function getServerLocale(locale: string | undefined): Locale {
  if (locale !== undefined && (SUPPORTED_LOCALES as readonly string[]).includes(locale)) {
    return locale as Locale;
  }

  return DEFAULT_LOCALE;
}

export { dictionaries, en, es };
export type { Locale, Translations };
