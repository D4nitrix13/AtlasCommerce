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
  | 'morales-store';

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
 * Panda ya tiene sus 4 verticales acordadas en §7 del plan
 * (individuos, eventos, empresas, equipos). Los otros 3 tenants
 * quedan con array vacío hasta sus commits siguientes (3 y 4 del
 * plan); el componente `UseCases.astro` es empty-safe y no renderiza
 * la sección si el array está vacío.
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
  kitsune: [],
  'mm-boutique': [],
  'morales-store': [],
} as const;

/**
 * Devuelve los use cases del tenant o array vacío si no hay match.
 *
 * Acepta `string | null | undefined` para ser tolerante con el valor
 * que llega de `Astro.locals.tenant?.tenantSlug ?? null` sin que el
 * caller tenga que hacer un guard.
 */
export function getUseCasesForTenant(
  slug: string | null | undefined,
): readonly LocalizableUseCase[] {
  if (!slug) return [];
  if (slug in TENANT_USE_CASES) {
    return TENANT_USE_CASES[slug as TenantSlug];
  }
  return [];
}
