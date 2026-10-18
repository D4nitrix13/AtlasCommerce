/**
 * Tenant-specific home data — use cases por tienda.
 *
 * Mock data: se reemplaza por fetch cuando el endpoint backend exista
 * (lo más probable, `GET /api/storefront/tenants/{slug}/home`). La shape
 * expuesta acá está alineada con el componente `UseCases.astro`, que
 * espera `cases: { iconKey, label, href }[]` ya traducidos.
 *
 * Por qué existe un wrapper `LocalizableUseCase` y no se devuelve
 * directamente la shape del componente:
 *   El data layer no debe conocer el diccionario i18n. Pasar
 *   `labelKey` (clave) en lugar de `label` (texto traducido) mantiene
 *   la separación data ↔ presentación: el caller resuelve la clave
 *   con el dictionary `t` que ya cargó. Mismo patrón que
 *   `StorefrontFooterNav.astro` (FooterLink.labelKey).
 *
 * Por qué `Record<TenantSlug, ...>` y no `Partial<Record<...>>`:
 *   Fuerza a que cada tenant esté explícitamente en el mapa. Un
 *   tenant sin use cases todavía debe aparecer con `[]` — esto
 *   previene el bug silencioso de olvidar un slug al agregar un
 *   tenant nuevo (Commit 3+4). Ver plan §7 (tabla de verticales).
 *
 * Iconos:
 *   `iconKey` se valida contra el set de `Icon.astro` en build time
 *   via Storybook + visual QA. El componente tiene fallback a
 *   `squares-2x2` para nombres desconocidos, así que un ícono mal
 *   escrito no rompe el build, pero sí da una pista visual para
 *   detectarlo.
 */
import type { TranslationKey } from '../i18n/types';

/** Slugs de tenants soportados por el storefront. */
export type TenantSlug =
  | 'panda-estampados'
  | 'kitsune'
  | 'mm-boutique'
  | 'morales-store'
  | 'instrumentos-musicales-la-voz';

/**
 * Use case "localizable": el caller traduce `labelKey` con su
 * dictionary `t` antes de pasarlo al componente `UseCases.astro`.
 */
export interface LocalizableUseCase {
  /** Clave que resuelve `Icon.astro`. Fallback: `squares-2x2`. */
  iconKey: string;
  /** Clave i18n a traducir antes de pasarla al componente. */
  labelKey: TranslationKey;
  /** Destino del vertical (categoría, landing, etc.). */
  href: string;
}

/**
 * Mapa de tenants a sus use cases de la home B2C.
 *
 * Cada tenant tiene 4 verticales acordadas en §7 del plan: Panda
 * segmenta por tipo de cliente, Kitsune por nicho otaku, MM-Boutique
 * por ocasión (boutique aspiracional: eventos, diario, trabajo,
 * accesorios) y Morales Store por demografía (tienda general:
 * mujer, hombre, niños, accesorios). El componente `UseCases.astro`
 * es empty-safe y no renderiza la sección si el array está vacío;
 * eso permite agregar un tenant nuevo en cualquier momento sin que
 * rompa la home.
 *
 * Iconos: todos los `iconKey` se validan contra el set existente en
 * `Icon.astro` (que tiene fallback a `squares-2x2` para nombres
 * desconocidos). MM-Boutique reutiliza iconos ya presentes (gift,
 * shopping-cart, building-office, tag). Morales Store usa star para
 * mujer porque el set no tiene iconos diferenciados por género:
 * star refleja el posicionamiento aspiracional de la sección,
 * aunque rompe la simetría con MM-Boutique — la elección se
 * documenta en el body del commit 4.
 */
export const TENANT_USE_CASES: Record<TenantSlug, readonly LocalizableUseCase[]> = {
  'panda-estampados': [
    {
      iconKey: 'user',
      labelKey: 'storefront.home.panda.useCase.individual',
      href: '/panda-estampados/products?segment=individuos',
    },
    {
      iconKey: 'gift',
      labelKey: 'storefront.home.panda.useCase.eventos',
      href: '/panda-estampados/products?segment=eventos',
    },
    {
      iconKey: 'building-office',
      labelKey: 'storefront.home.panda.useCase.empresas',
      href: '/panda-estampados/products?segment=empresas',
    },
    {
      iconKey: 'users',
      labelKey: 'storefront.home.panda.useCase.equipos',
      href: '/panda-estampados/products?segment=equipos',
    },
  ],
  kitsune: [
    {
      iconKey: 'cube',
      labelKey: 'storefront.home.kitsune.useCase.figures',
      href: '/products?category=figures',
    },
    {
      iconKey: 'eye',
      labelKey: 'storefront.home.kitsune.useCase.anime',
      href: '/products?category=anime',
    },
    {
      iconKey: 'document-text',
      labelKey: 'storefront.home.kitsune.useCase.manga',
      href: '/products?category=manga',
    },
    {
      iconKey: 'users',
      labelKey: 'storefront.home.kitsune.useCase.cosplay',
      href: '/products?category=cosplay',
    },
  ],
  'mm-boutique': [
    {
      iconKey: 'gift',
      labelKey: 'storefront.home.mm.useCase.eventos',
      href: '/products?segment=eventos',
    },
    {
      iconKey: 'shopping-cart',
      labelKey: 'storefront.home.mm.useCase.diario',
      href: '/products?segment=diario',
    },
    {
      iconKey: 'building-office',
      labelKey: 'storefront.home.mm.useCase.trabajo',
      href: '/products?segment=trabajo',
    },
    {
      iconKey: 'tag',
      labelKey: 'storefront.home.mm.useCase.accesorios',
      href: '/products?segment=accesorios',
    },
  ],
  'morales-store': [
    {
      iconKey: 'star',
      labelKey: 'storefront.home.morales.useCase.mujer',
      href: '/products?segment=mujer',
    },
    {
      iconKey: 'user',
      labelKey: 'storefront.home.morales.useCase.hombre',
      href: '/products?segment=hombre',
    },
    {
      iconKey: 'users',
      labelKey: 'storefront.home.morales.useCase.ninos',
      href: '/products?segment=ninos',
    },
    {
      iconKey: 'tag',
      labelKey: 'storefront.home.morales.useCase.accesorios',
      href: '/products?segment=accesorios',
    },
  ],
} as const;

/**
 * Devuelve los use cases del tenant o array vacío si no hay match.
 *
 * Acepta `string | null | undefined` para ser tolerante con el valor
 * que llega de `Astro.locals.tenant?.tenantSlug ?? null` sin que el
 * caller tenga que hacer un guard.
 */
export function getUseCasesForTenant(
  slug: string | null | undefined
): readonly LocalizableUseCase[] {
  if (!slug) return [];
  if (slug in TENANT_USE_CASES) {
    return TENANT_USE_CASES[slug as TenantSlug];
  }
  return [];
}
