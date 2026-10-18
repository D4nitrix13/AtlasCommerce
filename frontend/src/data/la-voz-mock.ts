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

export interface LaVozProductVariant {
  id: string;
  label: string;
  /** Hex color para swatch visual en cards/detalle. */
  hex?: string;
  /** Indica si esta variante esta disponible. */
  inStock: boolean;
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

/**
 * Genera un LaVozProduct a partir de un ProductMockSpec. Adapta la
 * shape del factory output al contrato LaVozProduct (que es el que
 * consume `la-voz-mock.ts` y los pages que lo usan).
 */
function createLaVozProductFromSpec(
  spec: ProductMockSpec,
  index: number,
): LaVozProduct {
  const product = createProductMock(spec, { id: 9000 + index });
  const defaultVariant = spec.variants.find((v) => v.isDefault) ?? spec.variants[0];

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
    // Gallery: front por cada variant (back se omite porque muchos specs
    // no tienen imagen back en assets/). El PDP lo usa para mostrar
    // imágenes reales de guitarra en lugar del placeholder T-shirt del
    // spread SABER_ALTER_DETAIL.
    images: spec.variants.map((variant) =>
      buildImageUrl(spec, variant.slug, 'front'),
    ),
    attributes: {
      brand: spec.brandSlug,
      specs: [
        { label: 'Marca', value: spec.brand },
        { label: 'Modelo', value: spec.name },
        // Specs adicionales se pueden extraer del spec si se necesita.
      ],
    },
    variants: spec.variants.map((v) => ({
      id: v.slug,
      label: v.displayName,
      hex: v.hex,
      inStock: true,
    })),
    inStock: true,
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