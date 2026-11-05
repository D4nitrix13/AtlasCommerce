/**
 * Contenido legal/informativo del storefront (legislación Argentina).
 * Un getter por página; el contenido se interpola con datos del tenant
 * via `LegalContext`. El chrome de UI (títulos, breadcrumbs) vive en
 * `i18n/locales/{es,en}.ts` bajo `storefront.legal.*`.
 */
export { getAboutContent } from './about';
export { getContactContent } from './contact';
export { getHelpContent } from './help';
export { getPrivacyContent } from './privacy';
export { getTermsContent } from './terms';
export type { LegalContext, LegalLocale, LegalPageContent, LegalSection, LegalSubSection } from './types';
