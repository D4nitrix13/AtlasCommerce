/**
 * Config de branding por tienda.
 *
 * Single source of truth para el frontend. Las páginas admin y
 * storefront leen de acá para aplicar el branding correcto según el
 * tenant resuelto por el middleware.
 *
 * Cuando el sistema crezca y se quiera config dinámico, este archivo
 * se reemplaza por una llamada a `/api/storefront/me` que devuelve
 * la config desde la tabla `store_settings`.
 */

import { kitsuneTheme } from '@theme/kitsune';
import { mmBoutiqueTheme } from '@theme/mm-boutique';
import { moralesStoreTheme } from '@theme/morales-store';
import { pandaTheme } from '@theme/panda';

export interface StoreBrand {
  slug: string;
  name: string;
  shortName: string;
  domains: readonly string[];
  adminDomains: readonly string[];
  logoPath: string;
  loginLogoPath: string;
  accentColor: string;
  accentHoverColor: string;
  supportEmail: string;
}

export const STORE_BRANDS: Record<string, StoreBrand> = {
  panda: pandaTheme.brand,
  kitsune: kitsuneTheme.brand,
  'mm-boutique': mmBoutiqueTheme.brand,
  'morales-store': moralesStoreTheme.brand,
};

export const DEFAULT_BRAND: StoreBrand = STORE_BRANDS.panda ?? {
  slug: 'default',
  name: 'Kitsune Store',
  shortName: 'Kitsune',
  domains: [],
  adminDomains: [],
  logoPath: '/images/stores/kitsune/brand/logo.svg',
  loginLogoPath: '/images/stores/kitsune/brand/logo.svg',
  accentColor: '#7C3AED',
  accentHoverColor: '#6D28D9',
  supportEmail: 'support@example.com',
};

export function getBrandBySlug(slug: string | null): StoreBrand {
  if (slug === null) {
    return DEFAULT_BRAND;
  }

  return STORE_BRANDS[slug] ?? DEFAULT_BRAND;
}

export function getAllAdminDomains(): readonly string[] {
  return Object.values(STORE_BRANDS).flatMap((brand) => brand.adminDomains);
}
