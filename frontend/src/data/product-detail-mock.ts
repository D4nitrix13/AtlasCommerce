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
import type { ProductHighlightsItem } from '../components/storefront/products/ProductHighlights.types';
import type { Product } from '../types/catalog';

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
  /** Total de likes (server-authoritative). */
  likesCount: number;
  /** Si el customer actual ya dio like. SSR: siempre false (server no
   *  conoce la sesión); el cliente lo actualiza tras el primer fetch. */
  likedByCurrentUser: boolean;
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
  /**
   * Si el producto expone el selector "Nuevo / Usado" en la PDP.
   * Default false (los mocks existentes asumen false). Productos
   * refurbished, ropa vintage, autos, antigüedades: true.
   */
  hasCondition?: boolean;
  /**
   * Si el producto expone el grid de tallas (S/M/L, 4/4/3/4, etc.)
   * en la PDP. Default false. Ropa, zapatos, instrumentos con
   * tamaños distintos: true.
   */
  hasSizes?: boolean;
  /** Galería: 4 imágenes mínimo (Baymard). */
  images: string[];
  /** Variantes estructuradas. */
  variantAttributes: ProductVariantAttribute[][];
  variants: ProductVariant[];
  /** Especificaciones clave→valor (resumen plano). */
  specs: { label: string; value: string }[];
  /** Especificaciones agrupadas por categoría (Gibson layout). */
  groupedSpecs?: { category: string; items: { label: string; value: string }[] }[];
  /** Highlights: par icono+etiqueta (Gibson layout). */
  highlights?: ProductHighlightsItem[];
  /** Opciones de financiamiento mostradas debajo del CTA (Gibson layout). */
  financing?: { syncMonthly: string; shopPayMonthly: string };
  /** Artículo/video destacado mostrado debajo de la descripción (Gibson layout). */
  featuredArticle?: { eyebrow: string; title: string; imageUrl: string; href: string };
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
  /**
   * Imagen alternativa para hover-swap (e.g., vista trasera/lateral del
   * producto). Si no está definida, la card se queda con la imagen
   * única sin swap.
   */
  imageUrlSecondary?: string;
  badges?: ('new' | 'sale' | 'low_stock')[];
  /** Marca/vendor del producto (línea 1 del subtítulo tipo Gibson). */
  vendor?: string;
  /** Acabado/color/variante principal (línea 2 del subtítulo). */
  finish?: string;
  /** Cantidad de variantes disponibles (mostrado como "5 acabados"). */
  variantsCount?: number;
  /**
   * Variantes del producto (mostradas como swatches/bolitas de color).
   *
   * Si cada variante tiene `imageUrl`, hover sobre su swatch hace
   * preview de esa variante (muestra la foto del producto en ese
   * color). Si falta `imageUrl`, el swatch es decorativo.
   */
  variants?: ReadonlyArray<{
    id: string;
    label: string;
    hex: string;
    imageUrl?: string;
  }>;
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
  // Saber Alter: figura coleccionable. Tiene tallas (S/M/L) pero no
  // condición nueva/usada (las figuras son nuevas por default).
  hasCondition: false,
  hasSizes: true,
  // 4 imágenes placeholder — gallery Baymard requiere ≥3.
  images: [PLACEHOLDER_IMAGE, PLACEHOLDER_IMAGE, PLACEHOLDER_IMAGE, PLACEHOLDER_IMAGE],
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
      likesCount: 15,
      likedByCurrentUser: false,
    },
    {
      id: 2,
      authorName: 'Marco A.',
      createdAt: '2026-07-28',
      rating: 4,
      title: 'Buena compra',
      body: 'Muy contenta con la figura. La base es estable y los hands intercambiables son un plus. Le bajo una estrella porque el envío tardó más de lo esperado.',
      verified: true,
      likesCount: 10,
      likedByCurrentUser: false,
    },
    {
      id: 3,
      authorName: 'Diego F.',
      createdAt: '2026-07-15',
      rating: 5,
      title: 'Perfecta para mi colección',
      body: 'Ya tenía otras figuras de Saber y esta es de las mejores. El color violeta en persona es aún más bonito que en las fotos.',
      verified: true,
      likesCount: 8,
      likedByCurrentUser: false,
    },
    {
      id: 4,
      authorName: 'Andrea S.',
      createdAt: '2026-06-30',
      rating: 3,
      title: 'Esperaba más',
      body: 'La figura está bien pero esperaba más accesorios. Por el precio, podría incluir una mano extra o efecto de Excalibur.',
      verified: false,
      likesCount: 12,
      likedByCurrentUser: false,
    },
    {
      id: 5,
      authorName: 'Pablo M.',
      createdAt: '2026-06-18',
      rating: 5,
      title: 'Calidad premium',
      body: 'La caja viene bien protegida y la figura no tiene defectos de pintura. Recomendado 100%.',
      verified: true,
      likesCount: 5,
      likedByCurrentUser: false,
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
    // Segunda toma (_02) — preview en hover.
    imageUrlSecondary: fenderImageUrl('ebony-black', '02', '400x183'),
    badges: PRODUCTS[1].badges,
    vendor: 'Fender',
    finish: 'Ultra Heavy Aged',
    variantsCount: 2,
    variants: [
      {
        id: 'ebony-black',
        label: 'Ebony Black',
        hex: '#0F0F0F',
        imageUrl: fenderImageUrl('ebony-black', '01', '400x183'),
      },
      {
        id: 'halo-metallic',
        label: 'Halo Metallic',
        hex: '#B8B5A8',
        imageUrl: fenderImageUrl('halo-metallic', '01', '400x183'),
      },
    ],
  },
  {
    id: 9101,
    slug: 'gibson-les-paul-supreme-blueberry-burst',
    name: 'Gibson Les Paul Supreme, Blueberry Burst, Exclusive',
    priceCents: 399900,
    currency: 'USD',
    // Gibson es SQUARE (1600×1600). Usamos la toma `03_Front.png`
    // (guitarra COMPLETA de frente, no la hero 01 que es más close-up
    // del body). Las copias WebP viven en public/images/products/.
    imageUrl:
      '/images/products/gibson-les-paul-supreme-blueberry-burst/blueberry-burst/gibson-les-paul-supreme-blueberry-burst-blueberry-burst-1600x1600-03.webp',
    vendor: 'Gibson',
    finish: 'Blueberry Burst',
    badges: ['new'],
  },
  {
    id: 9102,
    slug: 'fender-american-professional-telecaster',
    name: 'Fender American Professional Telecaster',
    priceCents: 169900,
    currency: 'USD',
    // Misma guitarra y pickup config (V-Mod III single-coil Tele);
    // cambia el body color. 7 variants Rosewood FB (3-CS, Olympic White,
    // Black, Natural, Oxblood, Dark Night, Aged Cherry Burst) y 2 Maple FB
    // (3-CS, Butterscotch Blonde) = 9 total. El nombre NO incluye el
    // color en el modelo (los cards muestran "Player Fusion Tele, COLOR").
    imageUrl:
      '/images/products/fender-american-professional-telecaster/3-color-sunburst/fender-american-professional-telecaster-3-color-sunburst-1600x525-01.webp',
    imageUrlSecondary:
      '/images/products/fender-american-professional-telecaster/3-color-sunburst/fender-american-professional-telecaster-3-color-sunburst-1600x521-02.webp',
    vendor: 'Fender',
    finish: '3-Color Sunburst',
    variantsCount: 9,
    badges: ['new'],
    variants: [
      {
        id: '3-color-sunburst',
        label: '3-Color Sunburst',
        hex: '#3B2410',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-3-color-sunburst.webp',
      },
      {
        id: 'olympic-white',
        label: 'Olympic White',
        hex: '#F4F0E6',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-olympic-white.webp',
      },
      {
        id: 'black',
        label: 'Black',
        hex: '#0A0A0A',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-black.webp',
      },
      {
        id: 'natural',
        label: 'Natural',
        hex: '#C7A66B',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-natural.webp',
      },
      {
        id: 'oxblood',
        label: 'Oxblood',
        hex: '#5C1F22',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-oxblood.webp',
      },
      {
        id: 'dark-night',
        label: 'Dark Night',
        hex: '#1B2533',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-dark-night.webp',
      },
      {
        id: 'aged-cherry-burst',
        label: 'Aged Cherry Burst',
        hex: '#7C2A26',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-aged-cherry-burst.webp',
      },
      {
        id: '3-color-sunburst-maple',
        label: '3-Color Sunburst (Maple)',
        hex: '#3B2410',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-3-color-sunburst-maple.webp',
      },
      {
        id: 'butterscotch-blonde-maple',
        label: 'Butterscotch Blonde (Maple)',
        hex: '#D4A557',
        imageUrl:
          '/images/products/fender-american-professional-telecaster/fender-american-professional-telecaster/fender-american-professional-telecaster-fender-american-professional-telecaster-1600x525-butterscotch-blonde-maple.webp',
      },
    ],
  },
  {
    id: 9130,
    slug: 'fender-american-professional-stratocaster',
    name: 'Fender American Professional Stratocaster',
    priceCents: 169900,
    currency: 'USD',
    // 7 variants: 5 Rosewood FB (3-CS, Olympic White, Black, Oxblood,
    // Dark Night) + 2 Maple FB (3-CS, Olympic White). 3 single-coil V-Mod III
    // + trémolo 2-point.
    imageUrl:
      '/images/products/fender-american-professional-stratocaster/3-color-sunburst/fender-american-professional-stratocaster-3-color-sunburst-1600x523-01.webp',
    imageUrlSecondary:
      '/images/products/fender-american-professional-stratocaster/3-color-sunburst/fender-american-professional-stratocaster-3-color-sunburst-1600x521-02.webp',
    vendor: 'Fender',
    finish: '3-Color Sunburst',
    variantsCount: 7,
    badges: ['new'],
    variants: [
      {
        id: '3-color-sunburst',
        label: '3-Color Sunburst',
        hex: '#3B2410',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-3-color-sunburst.webp',
      },
      {
        id: 'olympic-white',
        label: 'Olympic White',
        hex: '#F4F0E6',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-olympic-white.webp',
      },
      {
        id: 'black',
        label: 'Black',
        hex: '#0A0A0A',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-black.webp',
      },
      {
        id: 'oxblood',
        label: 'Oxblood',
        hex: '#5C1F22',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-oxblood.webp',
      },
      {
        id: 'dark-night',
        label: 'Dark Night',
        hex: '#1B2533',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-dark-night.webp',
      },
      {
        id: '3-color-sunburst-maple',
        label: '3-Color Sunburst (Maple)',
        hex: '#3B2410',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-3-color-sunburst-maple.webp',
      },
      {
        id: 'olympic-white-maple',
        label: 'Olympic White (Maple)',
        hex: '#F4F0E6',
        imageUrl:
          '/images/products/fender-american-professional-stratocaster/fender-american-professional-stratocaster/fender-american-professional-stratocaster-fender-american-professional-stratocaster-1600x523-olympic-white-maple.webp',
      },
    ],
  },
  {
    id: 9140,
    slug: 'fender-american-professional-jaguar',
    name: 'Fender American Professional Jaguar',
    priceCents: 189900,
    currency: 'USD',
    // 5 variants Rosewood FB (3-CS, Olympic White, Black, Oxblood,
    // Dark Night). Scale 24" corto + 1 humbucker + 1 SC + trémolo
    // Panorama. (Distinto del Jazzmaster — el user confirmó Jaguar.)
    imageUrl:
      '/images/products/fender-american-professional-jaguar/3-color-sunburst/fender-american-professional-jaguar-3-color-sunburst-1600x545-01.webp',
    imageUrlSecondary:
      '/images/products/fender-american-professional-jaguar/3-color-sunburst/fender-american-professional-jaguar-3-color-sunburst-1600x543-02.webp',
    vendor: 'Fender',
    finish: '3-Color Sunburst',
    variantsCount: 5,
    badges: ['new'],
    variants: [
      {
        id: '3-color-sunburst',
        label: '3-Color Sunburst',
        hex: '#3B2410',
        imageUrl:
          '/images/products/fender-american-professional-jaguar/fender-american-professional-jaguar/fender-american-professional-jaguar-fender-american-professional-jaguar-1600x545-3-color-sunburst.webp',
      },
      {
        id: 'olympic-white',
        label: 'Olympic White',
        hex: '#F4F0E6',
        imageUrl:
          '/images/products/fender-american-professional-jaguar/fender-american-professional-jaguar/fender-american-professional-jaguar-fender-american-professional-jaguar-1600x545-olympic-white.webp',
      },
      {
        id: 'black',
        label: 'Black',
        hex: '#0A0A0A',
        imageUrl:
          '/images/products/fender-american-professional-jaguar/fender-american-professional-jaguar/fender-american-professional-jaguar-fender-american-professional-jaguar-1600x545-black.webp',
      },
      {
        id: 'oxblood',
        label: 'Oxblood',
        hex: '#5C1F22',
        imageUrl:
          '/images/products/fender-american-professional-jaguar/fender-american-professional-jaguar/fender-american-professional-jaguar-fender-american-professional-jaguar-1600x545-oxblood.webp',
      },
      {
        id: 'dark-night',
        label: 'Dark Night',
        hex: '#1B2533',
        imageUrl:
          '/images/products/fender-american-professional-jaguar/fender-american-professional-jaguar/fender-american-professional-jaguar-fender-american-professional-jaguar-1600x545-dark-night.webp',
      },
    ],
  },
  {
    id: 9120,
    slug: 'fender-american-vintage-ii-1957-stratocaster',
    name: 'Fender American Vintage II 1957 Stratocaster, Black',
    priceCents: 239900,
    currency: 'USD',
    // Fender AmVintage II 1957 Strat: aspect LANDSCAPE 1600×525.
    // Front específico del SKU Black (0110232806); back es compartido
    // entre variants del modelo (la 2-Color Sunburst SKU tiene el mismo
    // back). Las imágenes del recon round 1 (Oct 2026) — recon vía webfetch
    // porque /products.json está bloqueado por Cloudflare.
    imageUrl:
      '/images/products/fender-american-vintage-ii-1957-stratocaster/black/fender-american-vintage-ii-1957-stratocaster-black-1600x518-01.webp',
    imageUrlSecondary:
      '/images/products/fender-american-vintage-ii-1957-stratocaster/black/fender-american-vintage-ii-1957-stratocaster-black-1600x523-02.webp',
    vendor: 'Fender',
    finish: 'Black',
    badges: ['new'],
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
    vendor: 'Studio Ghibli',
    finish: '30 cm',
    variantsCount: 1,
  },
  {
    id: PRODUCTS[2].id,
    slug: PRODUCTS[2].slug,
    name: PRODUCTS[2].name,
    priceCents: PRODUCTS[2].priceCents,
    currency: PRODUCTS[2].currency ?? 'USD',
    imageUrl: PRODUCTS[2].imageUrl,
    badges: PRODUCTS[2].badges,
    vendor: 'Good Smile Company',
    finish: '10 cm',
    variantsCount: 1,
  },
  // === Gibson Mod™ Collection / Custom — 10 productos (recon batch) ===
  {
    id: 9103,
    slug: 'gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle',
    name: 'Custom 1968 Les Paul Custom Reissue, Gloss, White Sparkle',
    priceCents: 449900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle/gloss-white-sparkle/gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle-gloss-white-sparkle-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle/gloss-white-sparkle/gibson-custom-select-1968-les-paul-custom-reissue-gloss-white-sparkle-gloss-white-sparkle-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Gloss, White Sparkle',
    badges: ['new'],
  },
  {
    id: 9104,
    slug: 'gibson-mod-collection-es-335-smooth-sepia',
    name: 'Mod™ Collection ES-335, Smooth Sepia',
    priceCents: 299900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-es-335-smooth-sepia/smooth-sepia/gibson-mod-collection-es-335-smooth-sepia-smooth-sepia-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-es-335-smooth-sepia/smooth-sepia/gibson-mod-collection-es-335-smooth-sepia-smooth-sepia-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Smooth Sepia',
    badges: ['new'],
  },
  {
    id: 9105,
    slug: 'gibson-mod-collection-sg-standard-holy-shift',
    name: 'Mod™ Collection SG Standard, Holy Shift',
    priceCents: 189900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-sg-standard-holy-shift/holy-shift/gibson-mod-collection-sg-standard-holy-shift-holy-shift-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-sg-standard-holy-shift/holy-shift/gibson-mod-collection-sg-standard-holy-shift-holy-shift-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Holy Shift',
    badges: ['new'],
  },
  {
    id: 9106,
    slug: 'gibson-mod-collection-les-paul-standard-60s-hornet-burst',
    name: 'Mod™ Collection Les Paul Standard 60s, Hornet Burst',
    priceCents: 269900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-les-paul-standard-60s-hornet-burst/hornet-burst/gibson-mod-collection-les-paul-standard-60s-hornet-burst-hornet-burst-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-les-paul-standard-60s-hornet-burst/hornet-burst/gibson-mod-collection-les-paul-standard-60s-hornet-burst-hornet-burst-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Hornet Burst',
    badges: ['new'],
  },
  {
    id: 9107,
    slug: 'gibson-mod-collection-sg-modern-tenacity-blue',
    name: 'Mod™ Collection SG Modern, Tenacity Blue',
    priceCents: 229900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-sg-modern-tenacity-blue/tenacity-blue/gibson-mod-collection-sg-modern-tenacity-blue-tenacity-blue-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-sg-modern-tenacity-blue/tenacity-blue/gibson-mod-collection-sg-modern-tenacity-blue-tenacity-blue-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Tenacity Blue',
    badges: ['new'],
  },
  {
    id: 9108,
    slug: 'gibson-mod-collection-es-339-green-meme',
    name: 'Mod™ Collection ES-339, Green Meme',
    priceCents: 279900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-es-339-green-meme/green-meme/gibson-mod-collection-es-339-green-meme-green-meme-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-es-339-green-meme/green-meme/gibson-mod-collection-es-339-green-meme-green-meme-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Green Meme',
    badges: ['new'],
  },
  {
    id: 9109,
    slug: 'gibson-mod-collection-flying-v-80s-boisterous-blue',
    name: 'Mod™ Collection Flying V 80s, Boisterous Blue',
    priceCents: 219900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-flying-v-80s-boisterous-blue/boisterous-blue/gibson-mod-collection-flying-v-80s-boisterous-blue-boisterous-blue-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-flying-v-80s-boisterous-blue/boisterous-blue/gibson-mod-collection-flying-v-80s-boisterous-blue-boisterous-blue-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Boisterous Blue',
    badges: ['new'],
  },
  {
    id: 9110,
    slug: 'gibson-mod-collection-les-paul-junior-tropicalia',
    name: 'Mod™ Collection Les Paul Junior, Tropicalia',
    priceCents: 159900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-les-paul-junior-tropicalia/tropicalia/gibson-mod-collection-les-paul-junior-tropicalia-tropicalia-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-les-paul-junior-tropicalia/tropicalia/gibson-mod-collection-les-paul-junior-tropicalia-tropicalia-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Tropicalia',
    badges: ['new'],
  },
  {
    id: 9111,
    slug: 'gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite',
    name: 'Mod™ Collection Les Paul Custom 70s, Nighty-Nite Graphite',
    priceCents: 329900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite/nighty-nite-graphite/gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite-nighty-nite-graphite-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite/nighty-nite-graphite/gibson-mod-collection-les-paul-custom-70s-nighty-nite-graphite-nighty-nite-graphite-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Nighty-Nite Graphite',
    badges: ['new'],
  },
  {
    id: 9112,
    slug: 'gibson-mod-collection-flying-v-80s-flame-job-hot-rod',
    name: 'Mod™ Collection Flying V 80s, Flame Job Hot Rod',
    priceCents: 249900,
    currency: 'USD',
    imageUrl:
      '/images/products/gibson-mod-collection-flying-v-80s-flame-job-hot-rod/flame-job-hot-rod/gibson-mod-collection-flying-v-80s-flame-job-hot-rod-flame-job-hot-rod-front-1600x1600-03.webp',
    imageUrlSecondary:
      '/images/products/gibson-mod-collection-flying-v-80s-flame-job-hot-rod/flame-job-hot-rod/gibson-mod-collection-flying-v-80s-flame-job-hot-rod-flame-job-hot-rod-back-1600x1600-04.webp',
    vendor: 'Gibson',
    finish: 'Flame Job Hot Rod',
    badges: ['new'],
  },
];

/**
 * Imagen base de la galería PDP — sirve las versiones WebP responsive
 * que el script `scripts/convert-product-images.sh` generó en
 * `frontend/public/images/products/{slug}/{variant}/{...}.webp`.
 *
 * Tamaños servidos (4 anchos, alto derivado del aspect ratio 2.19:1):
 *   - 1600x730  ← imagen principal de la PDP (default)
 *   - 1200x547  ← retina / pantallas HiDPI
 *   - 800x365   ← tablets
 *   - 400x183   ← thumbnails de la PDP y catálogo cards
 *
 * El frontend debe usar `<picture>` con `<source media>` + `<img srcset>`
 * para servir el tamaño correcto según viewport.
 */
function fenderImageUrl(
  productSlug: string,
  variant: string,
  index: string,
  size: '1600x730' | '1200x547' | '800x365' | '400x183' = '1600x730'
): string {
  // Path convention: /images/products/{productSlug}/{variant}/{productSlug}-{variant}-{size}-{index}.webp
  return `/images/products/${productSlug}/${variant}/${productSlug}-${variant}-${size}-${index}.webp`;
}

/**
 * Mock completo del producto Fender para la PDP.
 *
 * Coincide con el seeder backend
 * `Database\Seeders\FenderPlayerFusionBassSeeder`. Mantener ambos en
 * sync si se modifican URLs o nombres de variante.
 */
export const FENDER_PLAYER_FUSION_BASS_DETAIL: ProductDetailData = {
  id: 9001,
  slug: 'fender-player-fusion-bass-hh',
  name: 'Fender Limited Edition Player Fusion Precision Bass HH',
  description:
    'Bajo eléctrico Precision Bass edición limitada con doble humbucker, mástil de maple, cuerpo de aliso y electrónica personalizada. Acabado Ultra Heavy Aged y pastillas Custombucker hand-wound por el equipo de Master Built. Disponible en 2 acabados.',
  priceCents: 179900,
  compareAtPriceCents: 199900,
  currency: 'USD',
  category: 'bajo-electrico',
  stock: 12,
  badges: ['new'],
  // Bajo Fender: La Voz vende mayoria nuevos (hasCondition: true) pero
  // un bajo eléctrico adulto SIEMPRE es full-size (hasSizes: false).
  // Coincide con los flags del seeder backend
  // `FenderPlayerFusionBassSeeder::ensureProduct()`.
  hasCondition: true,
  hasSizes: false,
  images: [
    ...['01', '02', '03', '04', '05'].map((idx) => fenderImageUrl('ebony-black', idx, '1600x730')),
    ...['01', '02', '03', '04', '05'].map((idx) =>
      fenderImageUrl('halo-metallic', idx, '1600x730')
    ),
  ],
  variantAttributes: [
    [
      { type: 'color', id: 'ebony-black', label: 'Ebony Black', hex: '#0F0F0F' },
      { type: 'color', id: 'halo-metallic', label: 'Halo Metallic', hex: '#B8B5A8' },
    ],
  ],
  variants: [
    {
      id: 'fender-pfb-ebony',
      sku: 'FND-LPFB4-HH-EBONYBLACK',
      attributes: [{ type: 'color', id: 'ebony-black', label: 'Ebony Black', hex: '#0F0F0F' }],
      priceCents: 179900,
      imageUrl: fenderImageUrl('ebony-black', '01', '1600x730'),
      stock: 6,
    },
    {
      id: 'fender-pfb-halo',
      sku: 'FND-LPFB4-HH-HALOMETALLIC',
      attributes: [{ type: 'color', id: 'halo-metallic', label: 'Halo Metallic', hex: '#B8B5A8' }],
      priceCents: 179900,
      imageUrl: fenderImageUrl('halo-metallic', '01', '1600x730'),
      stock: 6,
    },
  ],
  // Highlights — bullets visuales con icono. Iconos disponibles:
  // award, sparkle, ruler, shield, star, music (ver ProductHighlights.astro).
  highlights: [
    { icon: 'premio', label: 'Cuerpo de aliso con alivio de peso' },
    { icon: 'regla', label: 'Mástil de maple con perfil en C moderno' },
    { icon: 'destello', label: 'Inlays de madreperla estilo vintage' },
    { icon: 'musica', label: 'Pastillas Custombucker HH hand-wound' },
    { icon: 'escudo', label: 'Tapa de maple figurado' },
    { icon: 'regla', label: 'Diapasón de pau ferro con 20 trastes' },
    { icon: 'musica', label: 'Electrónica HH con selector 3-way' },
    { icon: 'escudo', label: 'Estuche rígido incluido de serie' },
  ],
  // Specs por categoría — renderiza como acordeón colapsable.
  groupedSpecs: [
    {
      category: 'Cuerpo',
      items: [
        { label: 'Madera', value: 'Aliso (Alder)' },
        { label: 'Tapa', value: 'Maple figurado' },
        { label: 'Acabado', value: 'Ultra Heavy Aged (relíca 1957)' },
        { label: 'Forma', value: 'Precision Bass' },
        { label: 'Peso', value: '4.1 kg' },
      ],
    },
    {
      category: 'Mástil',
      items: [
        { label: 'Madera', value: 'Maple' },
        { label: 'Perfil', value: 'Custom en C moderno' },
        { label: 'Diapasón', value: 'Pau Ferro' },
        { label: 'Trastes', value: '20 Medium Jumbo' },
        { label: 'Inlays', value: 'Madreperla estilo vintage' },
        { label: 'Cejilla', value: 'Hueso sintético 42mm' },
      ],
    },
    {
      category: 'Electrónica',
      items: [
        { label: 'Pastillas', value: 'Custombucker HH hand-wound' },
        { label: 'Controles', value: 'Volumen + Tono + 3-way toggle' },
        { label: 'Selector', value: '3 posiciones (mástil/ambos/puente)' },
      ],
    },
    {
      category: 'Hardware',
      items: [
        { label: 'Puente', value: 'HiMass con selletas de acero' },
        { label: 'Cordal', value: 'Estilo vintage con cuerdas穿过 el cuerpo' },
        { label: 'Clavijero', value: 'Vintage-style open-gear' },
        { label: 'Clavijas', value: 'Acero niquelado' },
        { label: 'Pickguard', value: 'Pergamino blanco de 3 capas' },
      ],
    },
    {
      category: 'Miscelánea',
      items: [
        { label: 'Cuerdas', value: 'Fender 7250 .045–.105 (calibre bajo)' },
        { label: 'Estuche', value: 'Rígido moldeado con logo Fender' },
        { label: 'País de origen', value: 'USA (Corona, California)' },
        { label: 'Garantía', value: '2 años del fabricante' },
      ],
    },
  ],
  specs: [
    { label: 'SKU', value: 'FND-LPFB4-HH-2026' },
    { label: 'Categoría', value: 'Bajo eléctrico' },
    { label: 'Cuerpo', value: 'Aliso (Alder)' },
    { label: 'Mástil', value: 'Maple con perfil Custom' },
    { label: 'Diapasón', value: 'Pau Ferro' },
    { label: 'Trastes', value: '20 Medium Jumbo' },
    { label: 'Pastillas', value: 'Custombucker HH hand-wound' },
    { label: 'Electrónica', value: 'Volumen + Tono + 3-way toggle' },
    { label: 'Puente', value: 'HiMass' },
    { label: 'Clavijero', value: 'Vintage-style open-gear' },
    { label: 'Peso', value: '4.1 kg' },
  ],
  reviews: [
    {
      id: 1,
      authorName: 'Daniel P.',
      createdAt: '2026-08-05',
      rating: 4,
      title: 'Mástil increíble',
      body: 'El mástil es rápido y cómodo. Le quito una estrella porque el peso se siente al tocar de pie.',
      verified: true,
      likesCount: 12,
      likedByCurrentUser: false,
    },
    {
      id: 2,
      authorName: 'Laura S.',
      createdAt: '2026-08-22',
      rating: 5,
      title: 'Acabado precioso',
      body: 'El Halo Metallic en persona es espectacular. Vale cada centavo.',
      verified: true,
      likesCount: 18,
      likedByCurrentUser: false,
    },
    {
      id: 3,
      authorName: 'Roberto M.',
      createdAt: '2026-09-10',
      rating: 5,
      title: 'Sonido brutal',
      body: "Las pastillas Custombucker suenan increíbles. Mucho más cálidas que las de fábrica del heavy'ed anterior que tenía.",
      verified: true,
      likesCount: 22,
      likedByCurrentUser: false,
    },
    {
      id: 4,
      authorName: 'Carlos V.',
      createdAt: '2026-09-15',
      rating: 5,
      title: 'Pastillas hand-wound',
      body: 'Las Custombucker son exactamente lo que esperaba del custom shop. El pickup del mástil tiene un growl increíble.',
      verified: true,
      likesCount: 15,
      likedByCurrentUser: false,
    },
    {
      id: 5,
      authorName: 'María G.',
      createdAt: '2026-09-20',
      rating: 5,
      title: 'Ultra Heavy Aged',
      body: 'El relic es perfecto. Se ve tocado pero sin abuso. Los herrajes envejecidos lucen naturales.',
      verified: true,
      likesCount: 14,
      likedByCurrentUser: false,
    },
    {
      id: 6,
      authorName: 'Jorge L.',
      createdAt: '2026-09-25',
      rating: 4,
      title: 'Setup impecable',
      body: 'Llegó con acción baja y cejilla bien cortada. Solo ajusté un poco la curvatura del mástil.',
      verified: true,
      likesCount: 8,
      likedByCurrentUser: false,
    },
    {
      id: 7,
      authorName: 'Andrea P.',
      createdAt: '2026-10-01',
      rating: 3,
      title: 'Peso considerable',
      body: 'Es un bajo pesado (4.1 kg se siente). El sonido lo compensa pero requiere correa gruesa.',
      verified: true,
      likesCount: 6,
      likedByCurrentUser: false,
    },
    {
      id: 8,
      authorName: 'Sebastián R.',
      createdAt: '2026-10-05',
      rating: 5,
      title: 'Estuche de lujo',
      body: 'El estuche rígido incluido vale la pena. Forro interior aterciopelado, cerraduras de metal.',
      verified: true,
      likesCount: 10,
      likedByCurrentUser: false,
    },
  ],
};

/**
 * HTML del bloque de descripción largo del Fender (estilo Gibson).
 * Se pasa al `<ProductDetailsBlock intro={...}>` en la página.
 */
export const FENDER_PLAYER_FUSION_BASS_INTRO_HTML = `
  <p>Queda claro al instante que la Player Fusion Precision Bass HH no es un bajo cualquiera.</p>
  <p>Con un conjunto de características renovadas, está destinada a eclipsar todo lo demás y convertirse en uno de los lanzamientos más emocionantes de los últimos tiempos.</p>
  <p>Fender creó un instrumento de primera categoría que combina características de alto rendimiento con una estética deslumbrante.</p>
  <p>Un cuerpo de aliso con alivio de peso ultraligero para una comodidad de toque excepcional, coronado con un diapasón de pau ferro con 20 trastes medium jumbo.</p>
  <p>El mástil de maple tiene un perfil en C moderno diseñado para facilitar el acceso a los trastes superiores, adornado con impactantes inlays de madreperla.</p>
  <p>Las pastillas Custombucker HH hand-wound combinan potenciómetros de volumen y tono push/pull para una división flexible de bobinas y un control de tono maestro de las pastillas más agresivo.</p>
  <p>Esta configuración permite desde tonos cálidos al estilo Patent Applied For hasta sonidos más agresivos.</p>
  <p>Los herrajes están elegantemente realizados con un estuche rígido incluido, lo que convierte este bajo no solo en un instrumento flexible y de gran sonido, sino también en una pieza de colección.</p>
  <p>Con maderas de primera calidad, electrónica adaptable y acabados refinados, la Player Fusion Precision Bass HH redefine el concepto de guitarra "insignia".</p>
  <p>Para quienes buscan un instrumento impecable que destaque por su aspecto, tacto y sonido, esta se sitúa en la cima de la artesanía moderna de Fender.</p>
`;

/**
 * Heading del bloque de descripción (estilo Gibson — un claim, no genérico).
 */
export const FENDER_PLAYER_FUSION_BASS_HEADING = 'Tienes que tocar el Player Fusion';

/**
 * Mock del bundle "Frequently bought together" para el Fender Player
 * Fusion Precision Bass HH. Incluye el bajo + accesorios frecuentemente
 * comprados juntos (amplificador, strap, estuche, cable).
 */
export const FENDER_BUNDLE_ITEMS = [
  {
    product: {
      ...FENDER_PLAYER_FUSION_BASS_DETAIL,
      imageUrl:
        '/images/products/fender-player-fusion-bass-hh/ebony-black/fender-player-fusion-bass-hh-ebony-black-1600x730-01.webp',
    } as ProductDetailData,
    position: 1,
  },
  {
    product: {
      id: 9101,
      slug: 'fender-rumble-40-bass-combo-amp',
      name: 'Fender Rumble 40 Bass Combo',
      description: 'Amplificador combo para bajo de 40W con parlante de 10″.',
      priceCents: 39900,
      compareAtPriceCents: undefined,
      imageUrl: '/images/products/fender-rumble-40-amp/01.webp',
      category: 'bajo-electrico',
      stock: 8,
      badges: ['new'],
      currency: 'USD',
    },
    position: 2,
  },
  {
    product: {
      id: 9102,
      slug: 'fender-monster-cable-18-6',
      name: "Fender Monster Cable 18.6'",
      description: 'Cable profesional para instrumentos de 6 metros.',
      priceCents: 4900,
      compareAtPriceCents: undefined,
      imageUrl: '/images/products/fender-monster-cable/01.webp',
      category: 'accesorios',
      stock: 25,
      badges: ['new'],
      currency: 'USD',
    },
    position: 3,
  },
] as const;

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
  selected: Record<string, string>
): ProductVariant | null {
  return (
    variants.find((variant) =>
      variant.attributes.every((attr) => selected[attr.type] === attr.id)
    ) ?? null
  );
}
