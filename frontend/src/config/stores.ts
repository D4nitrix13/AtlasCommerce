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

export type StoreBrand = {
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
};

export const STORE_BRANDS: Record<string, StoreBrand> = {
  panda: {
    slug: "panda",
    name: "Panda Estampados",
    shortName: "Panda",
    domains: ["pandas-estampados.com", "www.pandas-estampados.com"],
    adminDomains: [
      "admin.pandas-estampados.com",
      "admin.localhost",
      "admin.127.0.0.1",
    ],
    logoPath: "/images/stores/panda-estampados/brand/logo.webp",
    loginLogoPath: "/images/stores/panda-estampados/brand/logo.webp",
    accentColor: "#DC2626",
    accentHoverColor: "#B91C1C",
    supportEmail: "support@panda-estampados.test",
  },
  kitsune: {
    slug: "kitsune",
    name: "Kitsune Store",
    shortName: "Kitsune",
    domains: ["kitsune.com", "www.kitsune.com"],
    adminDomains: [
      "admin.kitsune.com",
      "admin.kitsune.test",
    ],
    logoPath: "/images/stores/kitsune/brand/logo.svg",
    loginLogoPath: "/images/stores/kitsune/brand/logo.svg",
    accentColor: "#7C3AED",
    accentHoverColor: "#6D28D9",
    supportEmail: "support@kitsune.test",
  },
} as const;

export const DEFAULT_BRAND: StoreBrand = STORE_BRANDS.panda ?? {
  slug: "default",
  name: "Kitsune Store",
  shortName: "Kitsune",
  domains: [],
  adminDomains: [],
  logoPath: "/images/stores/kitsune/brand/logo.svg",
  loginLogoPath: "/images/stores/kitsune/brand/logo.svg",
  accentColor: "#7C3AED",
  accentHoverColor: "#6D28D9",
  supportEmail: "support@example.com",
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
