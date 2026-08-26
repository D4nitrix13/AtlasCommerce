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
import { pandaTheme } from '@theme/panda-estampados';

/**
 * Paleta neutra usada por las pantallas de autenticación (login,
 * register). Vive en el brand porque cada tienda puede tener su
 * propio modo (claro / oscuro) — Panda Estampados es blanco/gris,
 * las demás tiendas mantienen la experiencia dark slate histórica.
 *
 * Las claves siguen el patrón `rol/estado`:
 *   bg / surface          → fondos del panel y de los botones sociales
 *   border / borderStrong → bordes de inputs y separadores
 *   text / textMuted / textSubtle → texto principal, secundario y
 *                                   placeholders
 *   inputBg / inputBgFocus → fondos del input en reposo y al enfocar
 *   danger / dangerBg     → estados de error
 */
export interface LoginTheme {
  bg: string;
  surface: string;
  surfaceHover: string;
  border: string;
  borderStrong: string;
  text: string;
  textMuted: string;
  textSubtle: string;
  inputBg: string;
  inputBgFocus: string;
  danger: string;
  dangerText: string;
  dangerBg: string;
  divider: string;
}

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
  loginTheme: LoginTheme;
}

export const DARK_LOGIN_THEME: LoginTheme = {
  bg: '#020617',
  surface: 'rgba(30, 41, 59, 0.8)',
  surfaceHover: 'rgba(51, 65, 85, 0.8)',
  border: 'rgba(255, 255, 255, 0.15)',
  borderStrong: 'rgba(255, 255, 255, 0.4)',
  text: '#FFFFFF',
  textMuted: '#CBD5E1',
  textSubtle: '#64748B',
  inputBg: 'rgba(255, 255, 255, 0.05)',
  inputBgFocus: 'rgba(255, 255, 255, 0.10)',
  danger: '#F87171',
  dangerText: '#FCA5A5',
  dangerBg: 'rgba(127, 29, 29, 0.4)',
  divider: 'rgba(255, 255, 255, 0.15)',
};

export const LIGHT_LOGIN_THEME: LoginTheme = {
  bg: '#FFFFFF',
  surface: '#F5F5F5',
  surfaceHover: '#E5E5E5',
  border: '#E5E5E5',
  borderStrong: '#0A0A0A',
  text: '#0A0A0A',
  textMuted: '#525252',
  textSubtle: '#A3A3A3',
  inputBg: '#FFFFFF',
  inputBgFocus: '#FAFAFA',
  danger: '#DC2626',
  dangerText: '#B91C1C',
  dangerBg: '#FEF2F2',
  divider: '#E5E5E5',
};

export const STORE_BRANDS: Record<string, StoreBrand> = {
  'panda-estampados': pandaTheme.brand,
  kitsune: kitsuneTheme.brand,
  'mm-boutique': mmBoutiqueTheme.brand,
  'morales-store': moralesStoreTheme.brand,
};

export const DEFAULT_BRAND: StoreBrand = STORE_BRANDS['panda-estampados'] ?? {
  slug: 'default',
  name: 'Kitsune Store',
  shortName: 'Kitsune',
  domains: [],
  adminDomains: [],
  logoPath: '/api/storefront/assets/tenants/kitsune/branding/logo.svg',
  loginLogoPath: '/api/storefront/assets/tenants/kitsune/branding/logo.svg',
  accentColor: '#7C3AED',
  accentHoverColor: '#6D28D9',
  supportEmail: 'support@example.com',
  loginTheme: DARK_LOGIN_THEME,
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
