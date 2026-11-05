/**
 * backend-product-mapper — adapter del shape de
 * `StorefrontProductResource` (camelCase) al `Product` canónico de
 * `types/catalog.ts` que consumen las cards.
 *
 * Vive en `lib/` porque lo usan el PLP (`products/index.astro`) y el
 * home (`[locale]/index.astro`); antes era código local del PLP y el
 * home seguía sobre mocks.
 *
 * OJO: la API emite **camelCase** y `category`/`brand` vienen como
 * **string**, no como objetos anidados. El backend puede agregar
 * campos nuevos: este adapter los ignora a propósito (mejor que el
 * frontend truene).
 */
import type { Product, ProductImage, ProductVariant } from '../types/catalog';
import type { StorefrontPaginationMeta } from './pagination';

export interface BackendProductImage {
  url: string;
  altText: string;
  isPrimary: boolean;
  /**
   * La foto de hover la marca el admin (`is_hover` en `product_images`),
   * no el vocabulario del alt. Sin esta bandera la card no sabe cuál
   * imagen cambia al pasar el cursor.
   */
  isHover: boolean;
  /** El backend lo emite como int; la card lo compara contra `variants[].id`. */
  variantId: number | string | null;
  sortOrder: number;
  srcset: string;
}

export interface BackendProductVariant {
  id: number | string;
  name: string;
  finishSlug: string;
  finishLabel: string;
  finishHex: string | null;
  condition: string;
  stock: number;
  handedness: string | null;
}

export interface BackendProduct {
  id: number;
  slug: string;
  name: string;
  description: string;
  priceCents: number;
  imageUrl: string | null;
  /** Precio anterior en cents. Sólo presente si el producto está en oferta. */
  compareAtPriceCents?: number;
  /** Código ISO 4217. Ausente cuando el resource no eager-loadeó `currency`. */
  currency?: string;
  isExclusive?: boolean;
  stock: number;
  category: string;
  brand?: string;
  images?: BackendProductImage[];
  variants?: BackendProductVariant[];
}

/**
 * Envoltura paginada de `GET /api/v1/storefront/products`.
 *
 * `meta` es la fuente de verdad de la paginación: `total` y `last_page`
 * los calcula el backend sobre TODOS los productos del tenant.
 */
export interface StorefrontProductsResponse {
  data: BackendProduct[];
  meta: StorefrontPaginationMeta;
}

/**
 * Normaliza el id de variante a string. El backend lo emite como int
 * tanto en `images[].variantId` como en `variants[].id`, y
 * `deriveProductCardSwap` matchea swatch ↔ foto por igualdad estricta
 * (Map<string, ...>). Si uno queda number y el otro string, el lookup
 * falla en silencio: el swatch pierde su miniatura Y su image-swap.
 */
function normalizeVariantId(raw: number | string | null): string | null {
  if (raw === null) return null;
  return String(raw);
}

function mapBackendImage(image: BackendProductImage): ProductImage {
  return {
    url: image.url,
    altText: image.altText,
    isPrimary: image.isPrimary,
    isHover: image.isHover,
    variantId: normalizeVariantId(image.variantId),
    sortOrder: image.sortOrder,
    srcset: image.srcset,
  };
}

function mapBackendVariant(variant: BackendProductVariant): ProductVariant {
  return {
    // `normalizeVariantId` nunca devuelve null acá (`id` es required),
    // pero el shape lo pide `string`: coalesce explícito, no `as string`.
    id: normalizeVariantId(variant.id) ?? '',
    name: variant.name,
    finishSlug: variant.finishSlug,
    finishLabel: variant.finishLabel,
    finishHex: variant.finishHex,
    condition: variant.condition,
    stock: variant.stock,
    handedness: variant.handedness,
  };
}

// El listado público puede mandar `images` / `variants` ausentes (producto
// recién publicado, todavía sin fotos). El default a array vacío vive acá
// y no en `mapBackendToProduct` para no sumar ramas a una función que ya
// roza el umbral de complejidad.
function mapBackendImages(images: readonly BackendProductImage[] | undefined): ProductImage[] {
  return (images ?? []).map(mapBackendImage);
}

function mapBackendVariants(
  variants: readonly BackendProductVariant[] | undefined,
): ProductVariant[] {
  return (variants ?? []).map(mapBackendVariant);
}

/**
 * Adapter backend → `Product`. Mapea solo los campos que las cards
 * requieren. `stock` viene del recurso público; `inStock` se deriva.
 * `images` y `variants` SÍ se propagan: sin ellos la card no puede
 * mostrar la foto real ni los swatches de acabado.
 */
export function mapBackendToProduct(b: BackendProduct): Product {
  return {
    id: b.id,
    slug: b.slug,
    name: b.name,
    description: b.description ?? '',
    priceCents: b.priceCents,
    imageUrl: b.imageUrl ?? '/images/product-placeholder.png',
    ...(b.compareAtPriceCents !== undefined && {
      compareAtPriceCents: b.compareAtPriceCents,
    }),
    ...(b.currency !== undefined && { currency: b.currency }),
    images: mapBackendImages(b.images),
    variants: mapBackendVariants(b.variants),
    category: b.category,
    brand: b.brand ?? '',
    isExclusive: b.isExclusive ?? false,
    stock: b.stock,
    badges: [],
  };
}
