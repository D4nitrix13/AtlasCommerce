/**
 * Tipos del contenido legal/informativo del storefront.
 *
 * El contenido legal (textos largos de términos, privacidad, etc.) vive
 * en módulos tipados por locale en vez del diccionario i18n plano: son
 * párrafos extensos que no son «chrome» de UI y que cambian por
 * legislación, no por traducción de interfaz. El chrome (títulos,
 * breadcrumbs, labels) sí va en `i18n/locales/{es,en}.ts`.
 *
 * El contenido se interpola con datos del tenant (nombre de marca,
 * email de soporte) mediante `LegalContext` — nunca se hardcodean
 * nombres ni datos fiscales.
 */
import type { Locale } from '../config';

export type LegalLocale = Locale;

/** Datos del tenant que se interpolan en el texto legal. */
export interface LegalContext {
  /** `brand.name` de `config/stores.ts`. */
  brandName: string;
  /** `brand.supportEmail` de `config/stores.ts`. */
  supportEmail: string;
}

export interface LegalSubSection {
  /** Encabezado h3. */
  heading: string;
  paragraphs?: string[];
  items?: string[];
}

export interface LegalSection {
  /** Encabezado h2. En el FAQ de Ayuda, la pregunta. */
  heading: string;
  /** Párrafos; opcional cuando la sección es solo lista. */
  paragraphs?: string[];
  /** Viñetas (<ul>). */
  items?: string[];
  /** Pasos numerados (<ol>) — procedimientos. */
  steps?: string[];
  subSections?: LegalSubSection[];
}

export interface LegalPageContent {
  /** Párrafos de introducción previos a la primera sección. */
  intro?: string[];
  sections: LegalSection[];
}
