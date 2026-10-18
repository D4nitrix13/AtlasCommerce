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
 */
export interface ProductVariant {
  /** ID estable (típicamente el variant slug del backend). */
  id: string;
  /** SKU de la variant. */
  sku: string;
  /** Slug del acabado (ej. `rojo`, `negro`, `sunburst`). */
  finishSlug?: string;
  /** Label legible del acabado (ej. `Sunburst`, `Negro`). */
  finishLabel?: string;
  /** Hex color para swatch visual. */
  finishHex?: string;
  /** Condición de la variant (`nuevo`, `usado`, `ambos`). */
  condition?: ProductCondition;
  /** Stock de esta variant específica. */
  stock: number;
  /** Slug del tamaño asociado (si aplica). */
  sizeSlug?: string;
  /** Precio en cents (override del base_price). */
  priceCents?: number;
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
   * Galería completa de imágenes (variants con sus front images).
   * Si está vacía o ausente, el PDP cae al fallback genérico.
   */
  images?: readonly string[];
  /** Slug de la categoría. FK lógico a `Category.slug`. */
  category: string;
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
