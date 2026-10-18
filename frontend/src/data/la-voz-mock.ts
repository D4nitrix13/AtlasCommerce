/**
 * la-voz-mock.ts — Mock data de La Voz Instrumentos (B2C nicho).
 *
 * Hasta que el endpoint backend exista, los datos viven acá. Mismo
 * patron que `data/products.ts` y `data/b2b-mock.ts`. Cuando el
 * backend exponga `/api/storefront/products` con scope por tenant,
 * este archivo se reemplaza por fetch.
 *
 * Los productos se generan desde `product-mock-specs.ts` (la misma
 * fuente que alimenta el seeder backend `ProductSeeder` via JSON)
 * vía `createProductMock()`. Esto garantiza que el frontend mock y
 * el backend seed usan exactamente los mismos specs.
 *
 * Categories del tenant (guitarras-electricas, bajos,
 * amplificadores, audio, accesorios) se mapean desde el category
 * slug del spec. Cuando merchandising defina las verticales finales
 * y agregue productos de bajos/amplificadores/audio, solo hay que
 * extender GUITAR_SPECS en product-mock-specs.ts con specs de
 * otras categorías.
 */

import {
  buildImageUrl,
  createProductMock,
  type ProductMockSpec,
} from '../lib/product-mock-factory';
import { GUITAR_SPECS } from '../lib/product-mock-specs';

export interface LaVozProductSpec {
  label: string;
  value: string;
}

/** Condición de venta (Phase 7). Espejo del enum backend `product_variants.condition`. */
export type LaVozProductCondition = 'nuevo' | 'usado' | 'ambos';

export interface LaVozProductVariant {
  id: string;
  label: string;
  /** Hex color para swatch visual en cards/detalle. */
  hex?: string;
  /** Indica si esta variante esta disponible. */
  inStock: boolean;
  // ─── Phase 7 — product schema rich ────────────────────────────────
  /** Slug del acabado (Phase 7). Backend: `product_variants.finish_slug`. */
  finishSlug?: string;
  /** Label legible del acabado (Phase 7). Backend: `finish_label`. */
  finishLabel?: string;
  /** Condición de venta (Phase 7). Backend: `product_variants.condition`. */
  condition?: LaVozProductCondition;
  /** Stock de esta variant (Phase 7). Backend: `product_variants.stock`. */
  stock?: number;
}

/** Talla opcional del producto (Phase 7). */
export interface LaVozProductSize {
  slug: string;
  label: string;
  position?: number;
}

export interface LaVozProduct {
  id: string;
  slug: string;
  name: string;
  description: string;
  priceCents: number;
  compareAtPriceCents: number | null;
  currency: 'NIO' | 'USD';
  category: 'guitarras-electricas' | 'bajos' | 'amplificadores' | 'audio' | 'accesorios';
  badges: readonly ('new' | 'sale' | 'lowStock')[];
  imageUrl: string;
  imageAlt: string;
  /**
   * Galería completa de imágenes (front + back por variant).
   * Si está vacía, el PDP cae al fallback genérico (placeholder).
   */
  images: readonly string[];
  attributes: {
    brand: string;
    specs: readonly LaVozProductSpec[];
  };
  variants: readonly LaVozProductVariant[];
  inStock: boolean;
  // ─── Phase 7 — product schema rich ────────────────────────────────
  /** Bandera "Edición Limitada" (Phase 7). Default `false`. */
  isExclusive?: boolean;
  /**
   * Tallas del producto (Phase 7). Vacío/ausente para guitarras
   * eléctricas. Cuando está definido, el PDP renderiza el grid
   * `ProductSizeGrid` con estas opciones.
   */
  sizes?: readonly LaVozProductSize[];
}

export const LA_VOZ_CATEGORIES = [
  { slug: 'guitarras-electricas', label: 'Guitarras eléctricas', count: 0 },
  { slug: 'bajos', label: 'Bajos', count: 0 },
  { slug: 'amplificadores', label: 'Amplificadores', count: 0 },
  { slug: 'audio', label: 'Audio profesional', count: 0 },
  { slug: 'accesorios', label: 'Accesorios', count: 0 },
] as const;

export const LA_VOZ_BRANDS = [
  { slug: 'fender', label: 'Fender', count: 0 },
  { slug: 'gibson', label: 'Gibson', count: 0 },
] as const;

/**
 * Mapea category-slug del spec al enum LaVozProduct.category.
 * Solo guitarras-electricas está poblado por ahora (los specs existentes
 * son guitarras). Cuando se agreguen specs de otras categorías, extender
 * el switch.
 */
function mapCategorySlug(slug: string): LaVozProduct['category'] {
  switch (slug) {
    case 'guitarra-electrica':
      return 'guitarras-electricas';
    case 'bajo':
      return 'bajos';
    case 'amplificador':
      return 'amplificadores';
    case 'audio':
      return 'audio';
    case 'accesorio':
      return 'accesorios';
    default:
      return 'guitarras-electricas';
  }
}

/** Specs por defecto del product detail para La Voz (label/value). */
function defaultLaVozSpecs(spec: ProductMockSpec): readonly LaVozProductSpec[] {
  return [
    { label: 'Marca', value: spec.brand },
    { label: 'Modelo', value: spec.name },
  ];
}

/** Imágenes front por variant (gallery para el PDP). */
function buildLaVozImages(spec: ProductMockSpec): readonly string[] {
  return spec.variants.map((variant) => buildImageUrl(spec, variant.slug, 'front'));
}

/**
 * Mapea un variant del spec a LaVozProductVariant. Phase 7 propaga
 * los nuevos campos (`finishSlug`, `finishLabel`, `condition`, `stock`)
 * cuando están definidos en el spec.
 */
function mapSpecVariantToLaVozVariant(
  spec: ProductMockSpec,
): readonly LaVozProductVariant[] {
  return spec.variants.map((v) => ({
    id: v.slug,
    label: v.displayName,
    hex: v.hex,
    inStock: true,
    ...(v.finishSlug !== undefined && { finishSlug: v.finishSlug }),
    ...(v.finishLabel !== undefined && { finishLabel: v.finishLabel }),
    ...(v.condition !== undefined && { condition: v.condition }),
    ...(v.stock !== undefined && { stock: v.stock }),
  }));
}

/**
 * Genera un LaVozProduct a partir de un ProductMockSpec. Adapta la
 * shape del factory output al contrato LaVozProduct (que es el que
 * consume `la-voz-mock.ts` y los pages que lo usan).
 *
 * Phase 7: popula `isExclusive`, `sizes` y los nuevos campos de
 * variant (`finishSlug`, `finishLabel`, `condition`, `stock`) desde
 * el spec cuando están definidos.
 */
function createLaVozProductFromSpec(
  spec: ProductMockSpec,
  index: number,
): LaVozProduct {
  const product = createProductMock(spec, { id: 9000 + index });
  const sizes: readonly LaVozProductSize[] | undefined =
    spec.sizes !== undefined && spec.sizes.length > 0
      ? spec.sizes.map((size) => ({
          slug: size.slug,
          label: size.label,
          ...(size.position !== undefined && { position: size.position }),
        }))
      : undefined;

  return {
    id: String(product.id),
    slug: product.slug,
    name: product.name,
    description: product.description,
    priceCents: product.priceCents,
    compareAtPriceCents: null,
    currency: 'USD',
    category: mapCategorySlug(spec.categorySlug),
    badges: spec.badges as LaVozProduct['badges'],
    imageUrl: product.imageUrl,
    imageAlt: `${product.name} — vista frontal`,
    images: buildLaVozImages(spec),
    attributes: { brand: spec.brandSlug, specs: defaultLaVozSpecs(spec) },
    variants: mapSpecVariantToLaVozVariant(spec),
    inStock: true,
    ...(spec.isExclusive === true && { isExclusive: true }),
    ...(sizes !== undefined && { sizes }),
  };
}

export const LA_VOZ_PRODUCTS: readonly LaVozProduct[] = GUITAR_SPECS.map(
  createLaVozProductFromSpec,
);

// Helpers tipo PRODUCTS[] en data/products.ts para no romper la API.
export function findProductBySlug(slug: string): LaVozProduct | null {
  return LA_VOZ_PRODUCTS.find((product) => product.slug === slug) ?? null;
}

export function getFeaturedLaVozProducts(limit: number): readonly LaVozProduct[] {
  return LA_VOZ_PRODUCTS.filter((p) => p.badges.includes('new') || p.badges.includes('sale')).slice(
    0,
    limit,
  );
}

export function getLaVozProductsByCategory(
  category: LaVozProduct['category'],
): readonly LaVozProduct[] {
  return LA_VOZ_PRODUCTS.filter((product) => product.category === category);
}