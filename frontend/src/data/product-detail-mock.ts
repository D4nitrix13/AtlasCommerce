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
   * Si `true`, la imagen se rota -90° para mostrar el instrumento en
   * vertical (body arriba, pala abajo). Pensado para productos como
   * guitarras/bajos cuyas fotos fuente son landscape pero el sujeto es
   * vertical — en una card cuadrada se verían con mucho padding.
   *
   * NO aplicar a placeholders, figuras, ni productos cuyo sujeto NO
   * sea vertical. Default: false (no se rota).
   */
  rotateImage?: boolean;
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
    // Segunda imagen para hover-swap (vista alternativa del producto).
    // En este mock usamos otra toma de la galería como "back/alternate".
    imageUrlSecondary:
      '/images/products/fender-player-fusion-bass-hh/ebony-black/fender-player-fusion-bass-hh-ebony-black-400x183-02.webp',
    // El bajo es vertical pero la foto fuente es landscape — rotamos
    // la card para que el instrumento se vea parado (body arriba, pala
    // abajo) sin padding lateral excesivo.
    rotateImage: true,
    badges: PRODUCTS[1].badges,
    vendor: 'Fender',
    finish: 'Ultra Heavy Aged',
    variantsCount: 2,
    variants: [
      {
        id: 'ebony-black',
        label: 'Ebony Black',
        hex: '#0F0F0F',
        // 400×183 thumbnail — suficiente para card cuadrada, ahorra bytes
        // vs la 1600×730 default.
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
  variant: 'ebony-black' | 'halo-metallic',
  index: string,
  size: '1600x730' | '1200x547' | '800x365' | '400x183' = '1600x730'
): string {
  return `/images/products/fender-player-fusion-bass-hh/${variant}/fender-player-fusion-bass-hh-${variant}-${size}-${index}.webp`;
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
