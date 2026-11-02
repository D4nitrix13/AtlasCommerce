/**
 * Tipos del dominio "catalog" — storefront público.
 *
 * Modelan cómo se ve un producto en la UI pública (ProductCard,
 * ProductGrid, product detail, cart). NO son los tipos de wire del
 * backend — esos viven en `types/api.ts` y `types/storefront.ts`.
 *
 * Por ahora el storefront consume mock data desde `data/products.ts`
 * con esta shape. Cuando el endpoint real exista, el mapper backend
 * → ProductCard se hace en el consumer.
 */

export type ProductBadge = 'new' | 'sale' | 'low_stock';

/**
 * Talla opcional de un producto.
 *
 * Modelada como slug URL-safe (ej. `4-4`, `s`, `m`, `l`) + label
 * legible para UI (ej. `4/4 (Full Size)`, `Small`). `position`
 * define el orden de renderizado en el grid (ascendente).
 *
 * Solo presente cuando el producto vende tallas (instrumentos de
 * cuerda como violín 4/4 vs 3/4, ropa, calzado). Guitarras eléctricas
 * y la mayoría del catálogo NO la exponen.
 */
export interface ProductSize {
  /** Slug URL-safe (ej. `4-4`, `s`). */
  slug: string;
  /** Label legible (ej. `4/4 (Full Size)`, `Small`). */
  label: string;
  /** Posición en el grid (ascendente). */
  position?: number;
}

/**
 * Imagen del producto en la shape rica que expone
 * `GET /api/v1/storefront/products` (variantes eager-loaded).
 *
 * Para la shape legacy de `images: readonly string[]` (sin metadata
 * por variant) Product sigue aceptando esa forma en `images`. Ver
 * `Product.images`.
 */
export interface ProductImage {
  /** URL de la imagen (proxy del asset storage). */
  url: string;
  /**
   * Alt text semántico. En guitarras viene como
   * "1942 Banner J 45 Reissue Vintage Sunburst (Epiphone) - vista front".
   *
   * YA NO DECIDE NADA: hasta el slice 1.5 la card deducía de acá la foto de
   * hover parseando el sufijo (" - vista back"). Esa era la etiqueta del
   * scraper, no una decisión del administrador. Ahora la ranura de hover
   * viaja explícita en `isHover` y el `altText` es sólo texto accesible.
   */
  altText: string;
  /** Es la imagen principal del producto (1 por producto). */
  isPrimary: boolean;
  /**
   * Es la foto de hover del producto: la que aparece al pasar el cursor
   * sobre la card (1 por producto, y nunca la misma que `isPrimary`).
   *
   * El backend replica la marca en todas las resoluciones de la foto, pero
   * el storefront contrae cada foto a UNA entrada de galería —la de mayor
   * `width`, con el `srcset` de las demás—, así que el cliente ve
   * exactamente una entrada con el flag en `true`, y su `srcset` es el de
   * esa foto. No hace falta reconstruir nada para pintar el swap.
   */
  isHover: boolean;
  /**
   * Variant al que pertenece la imagen (acabado). `null` cuando la
   * imagen no se pudo asociar a ninguna variante (assets huérfanos
   * en el mock data); en ese caso la card cae al fallback genérico.
   */
  variantId: string | null;
  /** Orden estable para el orden de render cuando no hay `isPrimary`. */
  sortOrder: number;
  /**
   * `srcset` armado por el backend (cualquier resolución de la MISMA
   * foto). Vacío cuando el rol no tiene otras resoluciones → el
   * browser usa `src` solo. La card no necesita `srcset` agresivo
   * porque es un thumbnail chico (4/5 del column width del grid).
   */
  srcset: string;
}

/**
 * Variante para la card de catálogo (forma mínima).
 *
 * Cada variante representa un acabado/condición/tamaño específico del
 * producto con su propio stock. La forma rica del PDP vive en
 * `data/product-detail-mock.ts` como `ProductVariant` extendida con
 * `attributes` (color × size) para el picker color × size.
 *
 * Usada por `ProductCard` para renderizar swatches de variantes y
 * por el catálogo para mostrar rangos de precio cuando hay variants
 * con precios distintos.
 *
 * Alineada con `ProductApiVariant` de `lib/product-variant-selection.ts`
 * pero con tipos `string`-friendly: el backend manda `id: number` y
 * `stock: string` para la lista y `id: string` para el detalle, así
 * que este shape usa `string` y deja `stock` flexible. La card es
 * presentacional y nunca opera con estos valores, sólo los lee.
 */
export interface ProductVariant {
  /** ID estable. En el listado viene numérico; el detalle lo stringifica. */
  id: string;
  /** Nombre legible del acabado (Phase 9, viene del backend PDP). */
  name?: string;
  /**
   * SKU de la variant.
   *
   * Opcional a propósito: `GET /api/v1/storefront/products` NO lo expone
   * (`StorefrontProductResource::resolveVariants()` emite id, name,
   * finishSlug, finishLabel, finishHex, condition, stock y handedness, y
   * nada más). Las cards de catálogo no leen el SKU; el PDP sí lo muestra,
   * por eso su mapper coalesce a `''` en vez de inventar un valor.
   */
  sku?: string;
  /** Slug del acabado (ej. `rojo`, `negro`, `sunburst`). */
  finishSlug?: string;
  /** Label legible del acabado (ej. `Sunburst`, `Negro`). */
  finishLabel?: string;
  /**
   * Hex color para swatch visual. `null` (no `undefined`) cuando el
   * backend no conoce el color → la card cae a un dot slate.
   */
  finishHex?: string | null;
  /** Condición de venta (free-form string para tolerar nuevos valores). */
  condition?: string;
  /**
   * Stock de esta variant específica. Listado usa `string`
   * ('in_stock' | 'out_of_stock'), detalle usa `number`.
   */
  stock: number | string;
  /** Slug del tamaño asociado (si aplica). */
  sizeSlug?: string;
  /** Precio en cents (override del base_price). */
  priceCents?: number;
  /**
   * Mano. `null` = diestro (convención backend). `undefined` cuando
   * el backend no lo expone. Sólo útil en productos que ofrecen
   * ambas manos; la card lo ignora.
   */
  handedness?: string | null;
}

/** Condición de venta de un producto/variant. */
export type ProductCondition = 'nuevo' | 'usado' | 'ambos';

export interface Product {
  /** Identificador único. */
  id: number;
  /** Slug URL-friendly (ej. "saber-alter-figure"). */
  slug: string;
  /** Nombre localizado del producto. */
  name: string;
  /** Descripción corta localizada. */
  description: string;
  /** Precio en cents (ej. 2500 = $25.00). */
  priceCents: number;
  /** Precio anterior en cents (opcional, sólo si está en oferta). */
  compareAtPriceCents?: number;
  /** URL de la imagen principal. */
  imageUrl: string;
  /**
   * Galería completa de imágenes del producto.
   *
   * Acepta dos formas para tolerar los dos contratos del backend:
   *   - `readonly string[]` (legacy / listado sin variants eager).
   *   - `readonly ProductImage[]` (PDP / listado nuevo con
   *     `variantId` por imagen).
   *
   * Las cards que necesitan el `variantId` (para swatch → imagen
   * matching) filtran a `ProductImage[]`; las que sólo muestran
   * thumbnails (la card sin swatch) caen al `string[]`.
   */
  images?: readonly string[] | readonly ProductImage[];
  /** Slug de la categoría. FK lógico a `Category.slug`. */
  category: string;
  /** Nombre del brand (vacío si el backend no expone el join). */
  brand?: string;
  /** Stock disponible (0 = agotado). */
  stock: number;
  /** Badges opcionales para marketing ("Novedades", "Oferta", etc.). */
  badges?: ProductBadge[];
  /** Código de moneda ISO 4217 (default: 'USD'). */
  currency?: string;
  // ─── Phase 7 — product schema rich ────────────────────────────────
  /**
   * Bandera "Edición Limitada" / "Exclusive". Las cards y el PDP la
   * exponen con badge "Limitado" cuando es true.
   */
  isExclusive?: boolean;
  /**
   * Variantes del producto con su finish + condition + stock.
   * Las cards usan esto para renderizar swatches; el PDP lo usa
   * para el picker de acabado y el stock indicator.
   */
  variants?: readonly ProductVariant[];
  /**
   * Tallas del producto (sólo si vende por tallas, ej. violín 4/4).
   * Vacío/ausente para guitarras eléctricas y productos sin tallas.
   */
  sizes?: readonly ProductSize[];
}

export interface Category {
  /** Slug URL-friendly (ej. "figures"). */
  slug: string;
  /** Nombre localizado. */
  name: string;
  /** URL de imagen representativa (placeholder por ahora). */
  imageUrl: string;
  /** Href de la página de categoría. */
  href: string;
}
