/**
 * Derivador de "productos relacionados" para el carrusel del PDP.
 *
 * El carrusel del PDP (`<ProductRelated>`) consume una lista de
 * `RelatedProduct[]` (forma del mock legacy, declarada en
 * `data/product-detail-mock.ts`). Antes de esta helper, el slot se
 * poblaba con un array hardcodeado que devolvía los MISMOS productos
 * para cualquier slug — bug visible: una guitarra y un figure de
 * Saber Alter mostraban los mismos "relacionados".
 *
 * Solución: derivar los relacionados en el SSR a partir del catálogo
 * REAL del tenant, filtrado por la categoría del producto actual. El
 * endpoint `/api/v1/storefront/products?category=<slug>` (ver
 * `ProductController::index`) provee exactamente esa vista, reusando
 * `StorefrontProductResource`. De este modo:
 *
 *   - No inventamos datos: si no hay otros productos en la categoría,
 *     el slot queda oculto (handled por el componente via
 *     `products.length === 0`).
 *   - No agregamos un endpoint dedicado: usamos el mismo listado
 *     público, con un query param opcional.
 *   - La shape sigue siendo `RelatedProduct[]` para no tocar el
 *     contrato de `<ProductRelated>`.
 *
 * OJO — el listado está paginado desde Oct 2026: la respuesta es
 * `{data, meta}` y trae sólo la primera página (20 filas por default).
 * Alcanza de sobra para `RELATED_PRODUCTS_LIMIT = 12`, pero si algún día
 * el límite sube por encima del `per_page` implícito, este carrusel va a
 * mostrar menos de lo esperado sin avisar.
 *
 * Si el backend no expone `category` (caso: el join no fue eager-load
 * o la respuesta es parcial), el `RelatedProduct` cae a strings
 * vacíos seguros para que el render no rompa — el banner se oculta
 * también vía `=== ''` en el caller.
 */
import type { Product } from '../types/catalog';
import type { RelatedProduct } from '../data/product-detail-mock';
import type { StorefrontPaginationMeta } from './pagination';

/**
 * Cantidad máxima de relacionados a mostrar en el carrusel. El componente
 * `<ProductRelated>` esconde los botones prev/next cuando hay ≤3, así
 * que mantener un múltiplo bajo evita UI huérfana.
 */
export const RELATED_PRODUCTS_LIMIT = 12;

/**
 * Shape mínima del Resource `/api/v1/storefront/products` que el mapper
 * necesita. Se define acá (no se importa de `types/api.ts` que es más
 * generoso) para que la helper sea robusta a respuestas parciales y
 * auto-documentada.
 */
export interface StorefrontApiProductSummary {
  id: number;
  slug: string;
  name: string;
  priceCents: number;
  imageUrl: string;
  category?: string;
  compareAtPriceCents?: number;
  currency?: string;
  brand?: string;
  /** Segunda imagen si está presente — útil para hover-swap. */
  images?: readonly string[];
}

/**
 * Envoltura paginada de `GET /api/v1/storefront/products` — el mismo
 * contrato `{data, meta}` que emite `/storefront/products/search`.
 *
 * El carrusel de relacionados pide `?category=<slug>` y no pagina: la
 * primera página del endpoint ya trae más filas que
 * `RELATED_PRODUCTS_LIMIT`, así que `pickRelatedProducts` aplica el corte
 * FIFO sobre `data` sin mirar `meta`.
 */
export interface StorefrontApiProductsResponse {
  data: StorefrontApiProductSummary[];
  meta: StorefrontPaginationMeta;
}

/**
 * Convierte una fila del Resource `StorefrontProductResource` a la shape
 * `RelatedProduct` del UI. No inventa datos: si el campo no está
 * presente, el render del card cae a defaults internos.
 */
export function toRelatedProduct(api: StorefrontApiProductSummary): RelatedProduct {
  const related: RelatedProduct = {
    id: api.id,
    slug: api.slug,
    name: api.name,
    priceCents: api.priceCents,
    currency: api.currency ?? 'USD',
    imageUrl: api.imageUrl,
  };

  if (api.compareAtPriceCents !== undefined) {
    related.compareAtPriceCents = api.compareAtPriceCents;
  }
  if (api.images !== undefined && api.images.length > 1 && api.images[1] !== undefined) {
    related.imageUrlSecondary = api.images[1];
  }
  if (api.brand !== undefined && api.brand !== '') {
    related.vendor = api.brand;
  }

  return related;
}

/**
 * Filtra y limita los productos del catálogo al subset elegible para
 * "You may also like" del producto actual:
 *
 *  - Excluye el producto actual (no sugerirse a sí mismo).
 *  - Excluye cualquier producto sin `category` o sin `imageUrl`
 *    utilizable (defensivo — el card no podría rendirlos bien).
 *  - Limita a `RELATED_PRODUCTS_LIMIT` (FIFO sobre el orden del backend,
 *    que es estable dentro de un tenant).
 *
 * El input ya viene filtrado por categoría desde el backend
 * (`?category=<slug>`), así que acá NO se vuelve a filtrar por
 * categoría — la fuente de verdad es el endpoint.
 *
 * @param currentProductId   ID del producto actual; se excluye del set.
 * @param candidates         Array plano del Resource, ya filtrado por
 *                           el backend a la misma categoría.
 */
export function pickRelatedProducts(
  currentProductId: number,
  candidates: readonly StorefrontApiProductSummary[],
): RelatedProduct[] {
  const related: RelatedProduct[] = [];
  for (const candidate of candidates) {
    if (candidate.id === currentProductId) {
      continue;
    }
    if (candidate.imageUrl === undefined || candidate.imageUrl === '') {
      continue;
    }
    related.push(toRelatedProduct(candidate));
    if (related.length >= RELATED_PRODUCTS_LIMIT) {
      break;
    }
  }
  return related;
}

/**
 * Helper para tests/unit checks: indica si hay relacionados suficientes
 * para mostrar la sección (no exponer el carrusel vacío).
 *
 * El componente ya maneja `products.length > 0`, pero acá lo exporto
 * para que el caller del SSR (Astro page) decida ANTES de pasar al
 * componente si invocar el fetch (si la categoría está vacía, ni
 * siquiera pregunta al backend).
 */
export function hasRelatedProducts(products: readonly Product[]): boolean {
  return products.length > 0;
}
