/**
 * Mock data — product detail page (F4.2).
 *
 * Mock data expandido para soportar la página de detalle completa.
 * NO reemplaza el catálogo base de `data/products.ts` — sólo añade
 * el contexto necesario para variants + reviews + related. Cuando
 * el endpoint `GET /api/storefront/products/[slug]` exista, este
 * archivo se reemplaza por fetch + hidratación.
 *
 * Decisiones de diseño (Baymard research/product-page):
 *  - Variantes modeladas como matriz color × size con stock
 *    independiente (SKU clásico). Cada variant tiene imageUrl
 *    para que el gallery cambie al seleccionar color.
 *  - Reviews mockeados con `verified: true` y rating variado (3-5)
 *    para probar la lógica de promedio.
 *  - 4 productos relacionados coherentes con el género del producto
 *    de ejemplo (anime/figures/manga) — coherencia nicho kitsune.
 */
import { PRODUCTS } from './products';

const PLACEHOLDER_IMAGE = PRODUCTS[0].imageUrl;

/** Atributo de variante tipado. */
export interface ProductVariantAttribute {
  /** 'color' | 'size' | 'material' — extensible. */
  type: 'color' | 'size';
  /** ID estable (ej. "red", "m"). */
  id: string;
  /** Label localizado (ej. "Rojo", "M"). */
  label: string;
  /** Hex/CSS color para swatch (sólo color). */
  hex?: string;
}

/** Variante individual con SKU + stock + precio opcional. */
export interface ProductVariant {
  id: string;
  sku: string;
  attributes: ProductVariantAttribute[];
  priceCents: number;
  /** Si está definido, reemplaza el precio base del producto. */
  compareAtPriceCents?: number;
  imageUrl: string;
  stock: number;
}

/** Reseña individual. */
export interface ProductReview {
  id: number;
  authorName: string;
  /** ISO date (YYYY-MM-DD). */
  createdAt: string;
  rating: number;
  title: string;
  body: string;
  verified: boolean;
}

/** Producto expandido para la página de detalle. */
export interface ProductDetailData {
  id: number;
  slug: string;
  name: string;
  description: string;
  /** Precio base en cents (fallback si no hay variant con override). */
  priceCents: number;
  compareAtPriceCents?: number;
  currency: string;
  category: string;
  stock: number;
  badges: ('new' | 'sale' | 'low_stock')[];
  /** Galería: 4 imágenes mínimo (Baymard). */
  images: string[];
  /** Variantes estructuradas. */
  variantAttributes: ProductVariantAttribute[][];
  variants: ProductVariant[];
  /** Especificaciones clave→valor. */
  specs: { label: string; value: string }[];
  /** Reseñas. */
  reviews: ProductReview[];
}

/** Productos relacionados — mismo género otaku. */
export interface RelatedProduct {
  id: number;
  slug: string;
  name: string;
  priceCents: number;
  compareAtPriceCents?: number;
  currency: string;
  imageUrl: string;
  badges?: ('new' | 'sale' | 'low_stock')[];
}

/**
 * Producto de ejemplo completo para la página `/[locale]/products/[slug]`.
 *
 * Coherente con `PRODUCTS[0]` (Saber Alter Figma) — la página de
 * detalle puede consumir este mock o derivar del PRODUCTS extendiendo
 * las props que faltan.
 */
export const SABER_ALTER_DETAIL: ProductDetailData = {
  id: PRODUCTS[0].id,
  slug: PRODUCTS[0].slug,
  name: PRODUCTS[0].name,
  description: PRODUCTS[0].description,
  priceCents: PRODUCTS[0].priceCents,
  compareAtPriceCents: PRODUCTS[0].compareAtPriceCents,
  currency: PRODUCTS[0].currency ?? 'USD',
  category: PRODUCTS[0].category,
  stock: PRODUCTS[0].stock,
  badges: PRODUCTS[0].badges ?? [],
  // 4 imágenes placeholder — gallery Baymard requiere ≥3.
  images: [
    PLACEHOLDER_IMAGE,
    PLACEHOLDER_IMAGE,
    PLACEHOLDER_IMAGE,
    PLACEHOLDER_IMAGE,
  ],
  // Atributos: color × size.
  variantAttributes: [
    [
      { type: 'color', id: 'black', label: 'Negro', hex: '#1f2937' },
      { type: 'color', id: 'white', label: 'Blanco', hex: '#f8fafc' },
      { type: 'color', id: 'purple', label: 'Violeta', hex: '#7c3aed' },
    ],
    [
      { type: 'size', id: 's', label: 'S' },
      { type: 'size', id: 'm', label: 'M' },
      { type: 'size', id: 'l', label: 'L' },
    ],
  ],
  variants: [
    {
      id: 'black-s',
      sku: 'SA-FIG-BLK-S',
      attributes: [
        { type: 'color', id: 'black', label: 'Negro', hex: '#1f2937' },
        { type: 'size', id: 's', label: 'S' },
      ],
      priceCents: 14500,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 3,
    },
    {
      id: 'black-m',
      sku: 'SA-FIG-BLK-M',
      attributes: [
        { type: 'color', id: 'black', label: 'Negro', hex: '#1f2937' },
        { type: 'size', id: 'm', label: 'M' },
      ],
      priceCents: 14500,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 5,
    },
    {
      id: 'black-l',
      sku: 'SA-FIG-BLK-L',
      attributes: [
        { type: 'color', id: 'black', label: 'Negro', hex: '#1f2937' },
        { type: 'size', id: 'l', label: 'L' },
      ],
      priceCents: 14500,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 0,
    },
    {
      id: 'white-s',
      sku: 'SA-FIG-WHT-S',
      attributes: [
        { type: 'color', id: 'white', label: 'Blanco', hex: '#f8fafc' },
        { type: 'size', id: 's', label: 'S' },
      ],
      priceCents: 14500,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 2,
    },
    {
      id: 'white-m',
      sku: 'SA-FIG-WHT-M',
      attributes: [
        { type: 'color', id: 'white', label: 'Blanco', hex: '#f8fafc' },
        { type: 'size', id: 'm', label: 'M' },
      ],
      priceCents: 14500,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 4,
    },
    {
      id: 'purple-m',
      sku: 'SA-FIG-PUR-M',
      attributes: [
        { type: 'color', id: 'purple', label: 'Violeta', hex: '#7c3aed' },
        { type: 'size', id: 'm', label: 'M' },
      ],
      priceCents: 14900,
      compareAtPriceCents: 16900,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 1,
    },
    {
      id: 'purple-l',
      sku: 'SA-FIG-PUR-L',
      attributes: [
        { type: 'color', id: 'purple', label: 'Violeta', hex: '#7c3aed' },
        { type: 'size', id: 'l', label: 'L' },
      ],
      priceCents: 14900,
      imageUrl: PLACEHOLDER_IMAGE,
      stock: 3,
    },
  ],
  specs: [
    { label: 'SKU', value: 'SA-FIG-001' },
    { label: 'Categoría', value: 'Figures' },
    { label: 'Stock disponible', value: '8 unidades' },
    { label: 'Peso', value: '350 g' },
    { label: 'Dimensiones', value: '14 × 8 × 6 cm' },
    { label: 'Material', value: 'PVC + ABS articulado' },
  ],
  reviews: [
    {
      id: 1,
      authorName: 'Lucía R.',
      createdAt: '2026-08-12',
      rating: 5,
      title: '¡Hermosa figura!',
      body: 'Llegó en perfecto estado y la calidad de los acabados es increíble. Las articulaciones son firmes y el detalle del pelo es impresionante.',
      verified: true,
    },
    {
      id: 2,
      authorName: 'Marco A.',
      createdAt: '2026-07-28',
      rating: 4,
      title: 'Buena compra',
      body: 'Muy contenta con la figura. La base es estable y los hands intercambiables son un plus. Le bajo una estrella porque el envío tardó más de lo esperado.',
      verified: true,
    },
    {
      id: 3,
      authorName: 'Diego F.',
      createdAt: '2026-07-15',
      rating: 5,
      title: 'Perfecta para mi colección',
      body: 'Ya tenía otras figuras de Saber y esta es de las mejores. El color violeta en persona es aún más bonito que en las fotos.',
      verified: true,
    },
    {
      id: 4,
      authorName: 'Andrea S.',
      createdAt: '2026-06-30',
      rating: 3,
      title: 'Esperaba más',
      body: 'La figura está bien pero esperaba más accesorios. Por el precio, podría incluir una mano extra o efecto de Excalibur.',
      verified: false,
    },
    {
      id: 5,
      authorName: 'Pablo M.',
      createdAt: '2026-06-18',
      rating: 5,
      title: 'Calidad premium',
      body: 'La caja viene bien protegida y la figura no tiene defectos de pintura. Recomendado 100%.',
      verified: true,
    },
  ],
};

/**
 * Productos relacionados coherentes con el género otaku/anime del
 * producto principal (3 productos destacados — Baymard recomienda
 * entre 3-6).
 */
export const RELATED_PRODUCTS: readonly RelatedProduct[] = [
  {
    id: PRODUCTS[1].id,
    slug: PRODUCTS[1].slug,
    name: PRODUCTS[1].name,
    priceCents: PRODUCTS[1].priceCents,
    currency: PRODUCTS[1].currency ?? 'USD',
    imageUrl: PRODUCTS[1].imageUrl,
    badges: PRODUCTS[1].badges,
  },
  {
    id: PRODUCTS[6].id,
    slug: PRODUCTS[6].slug,
    name: PRODUCTS[6].name,
    priceCents: PRODUCTS[6].priceCents,
    compareAtPriceCents: PRODUCTS[6].compareAtPriceCents,
    currency: PRODUCTS[6].currency ?? 'USD',
    imageUrl: PRODUCTS[6].imageUrl,
    badges: PRODUCTS[6].badges,
  },
  {
    id: PRODUCTS[2].id,
    slug: PRODUCTS[2].slug,
    name: PRODUCTS[2].name,
    priceCents: PRODUCTS[2].priceCents,
    currency: PRODUCTS[2].currency ?? 'USD',
    imageUrl: PRODUCTS[2].imageUrl,
    badges: PRODUCTS[2].badges,
  },
];

/**
 * Helper: promedio de rating (redondeado a 1 decimal para mostrar).
 */
export function getAverageRating(reviews: readonly ProductReview[]): number {
  if (reviews.length === 0) return 0;
  const sum = reviews.reduce((total, review) => total + review.rating, 0);
  return Math.round((sum / reviews.length) * 10) / 10;
}

/**
 * Helper: encuentra una variant por atributos seleccionados.
 * Devuelve `null` si no hay match exacto.
 */
export function findVariantByAttributes(
  variants: readonly ProductVariant[],
  selected: Record<string, string>,
): ProductVariant | null {
  return (
    variants.find((variant) =>
      variant.attributes.every((attr) => selected[attr.type] === attr.id),
    ) ?? null
  );
}
