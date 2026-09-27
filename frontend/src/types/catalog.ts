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
  /** Slug de la categoría. FK lógico a `Category.slug`. */
  category: string;
  /** Stock disponible (0 = agotado). */
  stock: number;
  /** Badges opcionales para marketing ("Novedades", "Oferta", etc.). */
  badges?: ProductBadge[];
  /** Código de moneda ISO 4217 (default: 'USD'). */
  currency?: string;
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
