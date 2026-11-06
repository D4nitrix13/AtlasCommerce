/**
 * product-relations — helpers puros de los rieles de recomendación del
 * PDP ("Frecuentemente Comprados Juntos", "También Te Puede Gustar").
 *
 * El endpoint `GET /storefront/products/{slug}/relations` devuelve las
 * relaciones curadas en la shape `StorefrontProductResource`; el PDP las
 * convierte con `mapBackendToProduct` y acá las arma para el render:
 *
 *   - `toRelatedProductCard` proyecta un `Product` al `RelatedProduct`
 *     que consume `<ProductRelated>` (reutilizando el swap de swatches
 *     de `product-card-swap` — no hay un segundo adapter de backend).
 *   - `mergeAlikeProducts` implementa la cadena de fallback del rail
 *     "También Te Puede Gustar": curados primero, luego los derivados
 *     por categoría (`pickRelatedProducts`), sin duplicar y con tope.
 */
import type { RelatedProduct } from '../data/product-detail-mock';
import type { Product } from '../types/catalog';
import { deriveProductCardSwap, swatchFallbackHex } from './product-card-swap';

/** Identidad mínima para deduplicar rieles. */
export interface IdentifiedProduct {
  id: number;
}

/**
 * Mergea listas de productos sin repetir ids: gana la primera
 * aparición y se preserva el orden de lectura (primero la lista 1, etc).
 */
export function dedupeById<T extends IdentifiedProduct>(
  ...lists: readonly (readonly T[])[]
): T[] {
  const seen = new Set<number>();
  const merged: T[] = [];
  for (const list of lists) {
    for (const item of list) {
      if (seen.has(item.id)) continue;
      seen.add(item.id);
      merged.push(item);
    }
  }
  return merged;
}

/**
 * Cadena de fallback del rail "También Te Puede Gustar": los curados
 * (`you_may_also_like`) mandan en orden de `position`; el fallback
 * (relacionados por categoría) rellena los que falten, sin duplicados
 * y acotado a `limit`.
 */
export function mergeAlikeProducts<T extends IdentifiedProduct>(
  curated: readonly T[],
  fallback: readonly T[],
  limit: number,
): T[] {
  return dedupeById(curated, fallback).slice(0, Math.max(limit, 0));
}

/**
 * Proyección `Product` → `RelatedProduct` para el card de
 * `<ProductRelated>`. Los swatches (y su preview de imagen) salen del
 * mismo derivador que usa `ProductCard` — ya colapsados a un swatch por
 * acabado, así que acá sólo se proyectan a la shape del card. Sin
 * variantes o imágenes ricas degrada a la imagen única del producto.
 */
export function toRelatedProductCard(product: Product): RelatedProduct {
  const swap = deriveProductCardSwap(product);
  const variants = swap.swatches.map((swatch) => {
    // El chip representa al acabado: `finishLabel` gana sobre el `name`
    // de la variante representante (que arrastra la talla).
    const raw = product.variants?.find((variant) => variant.id === swatch.id);
    return {
      id: swatch.id,
      label: raw?.finishLabel ?? swatch.label,
      hex: swatchFallbackHex(swatch),
      ...(swatch.thumbnailUrl !== null && { imageUrl: swatch.thumbnailUrl }),
    };
  });
  const brand = product.brand ?? '';
  const finish = product.variants?.[0]?.finishLabel;

  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    priceCents: product.priceCents,
    currency: product.currency ?? 'USD',
    imageUrl: product.imageUrl,
    ...(product.compareAtPriceCents !== undefined && {
      compareAtPriceCents: product.compareAtPriceCents,
    }),
    ...(swap.hoverImage !== null && { imageUrlSecondary: swap.hoverImage.url }),
    // El card sólo pinta finish si también hay vendor (subtitle 2 líneas).
    ...(brand !== '' && { vendor: brand, finish }),
    ...(variants.length > 1 && { variantsCount: variants.length, variants }),
  };
}
