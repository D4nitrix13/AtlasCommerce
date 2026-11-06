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
import type { Product, ProductVariant } from '../types/catalog';
import type { ProductCardSwapData } from './product-card-swap';
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
 * mismo derivador que usa `ProductCard`; sin variantes o imágenes ricas
 * degrada a la imagen única del producto.
 */
/** Clave de agrupación de un acabado: slug|label|hex. */
function finishKey(raw: ProductVariant | undefined, hex: string): string {
  return `${raw?.finishSlug ?? ''}|${raw?.finishLabel ?? ''}|${hex}`;
}

/**
 * Colapsa los swatches a UNO POR ACABADO (clave slug|label|hex): las
 * tallas del mismo acabado comparten color y foto, y repetirlas
 * producía la fila de círculos idénticos que se leía como "imágenes
 * repetidas". Dentro de cada grupo se prefiere la variante con foto
 * propia — sin imagen el preview en hover sería invisible.
 */
function dedupeSwatchesByFinish(
  swatches: ProductCardSwapData['swatches'],
  rawVariants: readonly ProductVariant[],
): { id: string; label: string; hex: string; imageUrl?: string }[] {
  const byFinish = new Map<
    string,
    { id: string; label: string; hex: string; imageUrl?: string }
  >();
  swatches.forEach((swatch, index) => {
    const raw = rawVariants[index];
    const key = finishKey(raw, String(raw?.finishHex ?? ''));
    const entry = {
      id: swatch.id,
      label: raw?.finishLabel ?? swatch.label,
      hex: swatchFallbackHex(swatch),
      ...(swatch.thumbnailUrl !== null && { imageUrl: swatch.thumbnailUrl }),
    };
    const kept = byFinish.get(key);
    if (kept === undefined) {
      byFinish.set(key, entry);
      return;
    }
    if (kept.imageUrl === undefined && entry.imageUrl !== undefined) {
      byFinish.set(key, entry);
    }
  });
  return [...byFinish.values()];
}

export function toRelatedProductCard(product: Product): RelatedProduct {
  const swap = deriveProductCardSwap(product);
  const variants = dedupeSwatchesByFinish(swap.swatches, product.variants ?? []);
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
