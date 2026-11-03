/**
 * Mock data — productos y categorías del storefront público.
 *
 * Estos datos SON TEMPORALES: cuando el endpoint backend exista
 * (`GET /api/storefront/products`), este archivo se reemplaza por un
 * fetch real. La shape de `Product` y `Category` (en
 * `types/catalog.ts`) es lo único estable que cruza la frontera.
 *
 * Decisiones:
 *  - 12 productos cubren los estados visuales más importantes
 *    (con badge "new", "sale", "low_stock"; con y sin compareAtPrice).
 *  - Categorías alineadas al nicho kitsune (Figma / Nendoroid /
 *    Manga / Apparel / Accessories) — referencia design-refs §3.1
 *    (edición limitada, drops, fandom).
 *  - Imágenes: SVG generados (data URI) front+back por acabado vía
 *    `lib/catalog-product-factory` — mismo molde que La Voz para que
 *    hover-swap y swatches funcionen sin assets binarios. TODO:
 *    reemplazar por assets reales en F4 cuando exista CDN de storefront.
 *  - Acabados: color del personaje/serie (figuras, plush, apparel y
 *    accesorios llevan 2; el manga es monovariante porque un tomo no
 *    tiene colorways). El stock del seed se reparte entre los acabados
 *    para conservar el total por producto del mock anterior.
 */
import type { Category, Product } from '../types/catalog';
import { createCatalogProducts, type ProductSeed } from '../lib/catalog-product-factory';
import { LA_VOZ_PRODUCTS, type LaVozProduct } from './la-voz-mock';

/**
 * Adapter: convierte un LaVozProduct (shape del tenant La Voz) al
 * Product (shape canónico del storefront). Se usa para unificar los
 * catálogos en `PRODUCTS` y que la PDP pueda resolver slugs de
 * cualquier tenant via `findProductBySlug`.
 *
 * Solo se copian los campos que existen en ambas shapes. Los campos
 * específicos de LaVozProduct (variants con inStock, specs del
 * attributes) se exponen vía la-voz-mock.ts directamente.
 *
 * Phase 7: pasa `isExclusive` y `sizes` desde LaVozProduct a
 * Product. Los variants con `finishSlug`/`finishLabel`/`condition`/
 * `stock` se mapean al `ProductVariant` mínimo del catálogo
 * (`types/catalog.ts`) — el shape rico del PDP vive en
 * `data/product-detail-mock.ts`.
 */
function laVozProductToProduct(lv: LaVozProduct): Product {
  return {
    id: Number(lv.id) || 0,
    slug: lv.slug,
    name: lv.name,
    description: lv.description,
    priceCents: lv.priceCents,
    compareAtPriceCents: lv.compareAtPriceCents ?? undefined,
    imageUrl: lv.imageUrl,
    category: lv.category,
    stock: 1, // LaVozProduct no expone stock por variant; asumimos disponible
    badges: lv.badges.map((b) => (b === 'lowStock' ? 'low_stock' : b)) as ('new' | 'sale' | 'low_stock')[],
    currency: lv.currency,
    // Gallery complète (real para La Voz, placeholder para Kitsune que
    // no tienen gallery). Sin esta línea el PDP cae al fallback de
    // SABER_ALTER_DETAIL.images (4 T-shirts mockup).
    images: lv.images,
    ...(lv.isExclusive === true && { isExclusive: true }),
    variants: lv.variants.map((variant) => ({
      id: variant.id,
      sku: variant.id,
      // Phase 7: derivar finishSlug/finishLabel del id/label del variant
      // cuando no vienen seteados (fallback paralelo al factory upstream).
      finishSlug: variant.finishSlug ?? variant.id,
      finishLabel: variant.finishLabel ?? variant.label,
      ...(variant.hex !== undefined && { finishHex: variant.hex }),
      ...(variant.condition !== undefined && { condition: variant.condition }),
      stock: variant.stock ?? 1,
    })),
    ...(lv.sizes !== undefined &&
      lv.sizes.length > 0 && {
        sizes: lv.sizes.map((size) => ({
          slug: size.slug,
          label: size.label,
          ...(size.position !== undefined && { position: size.position }),
        })),
      }),
  };
}

const PLACEHOLDER_IMAGE =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="Product placeholder">
      <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#f1f5f9"/>
          <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
      </defs>
      <rect width="400" height="400" fill="url(#g)"/>
      <circle cx="200" cy="160" r="60" fill="#cbd5e1"/>
      <path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/>
      <text x="200" y="370" text-anchor="middle" font-family="system-ui, sans-serif" font-size="16" fill="#64748b">Image placeholder</text>
    </svg>`
  );

export const CATEGORIES: readonly Category[] = [
  {
    slug: 'figures',
    name: 'Figures',
    imageUrl: PLACEHOLDER_IMAGE,
    href: '/products?category=figures',
  },
  {
    slug: 'bajo-electrico',
    name: 'Bajo eléctrico',
    imageUrl: PLACEHOLDER_IMAGE,
    href: '/products?category=bajo-electrico',
  },
  {
    slug: 'manga',
    name: 'Manga',
    imageUrl: PLACEHOLDER_IMAGE,
    href: '/products?category=manga',
  },
  {
    slug: 'apparel',
    name: 'Apparel',
    imageUrl: PLACEHOLDER_IMAGE,
    href: '/products?category=apparel',
  },
  {
    slug: 'accessories',
    name: 'Accessories',
    imageUrl: PLACEHOLDER_IMAGE,
    href: '/products?category=accessories',
  },
] as const;

/**
 * Seeds de las 12 figuras Kitsune. `brand` es la serie/marca del
 * juguete (Good Smile para Figma/Nendoroid, la editorial del tomo para
 * manga, el estudio para merch). El stock por acabado suma el stock
 * total que tenía el mock anterior (p. ej. 8 → 4+4).
 */
const KITSUNE_SEEDS: readonly ProductSeed[] = [
  {
    id: 1,
    slug: 'saber-alter-figma',
    name: 'Saber Alter Figma',
    description:
      'Figma articulada de Saber Alter (Fate/Stay Night). 14cm, incluye base y manos intercambiables.',
    priceCents: 14500,
    compareAtPriceCents: 16900,
    category: 'figures',
    kind: 'figure',
    brand: 'Good Smile Company',
    finishes: [
      { slug: 'negro-armadura', label: 'Negro armadura', hex: '#1C1917', stock: 4 },
      { slug: 'violeta-magico', label: 'Violeta mágico', hex: '#4C1D95', stock: 4 },
    ],
    badges: ['new', 'sale'],
  },
  {
    id: 2,
    slug: 'nendoroid-zero-two',
    name: 'Nendoroid Zero Two',
    description: 'Nendoroid de Zero Two (Darling in the Franxx). 10cm, tres expresiones faciales.',
    priceCents: 7900,
    category: 'figures',
    kind: 'figure',
    brand: 'Good Smile Company',
    finishes: [
      { slug: 'rosa', label: 'Rosa', hex: '#EC4899', stock: 2 },
      { slug: 'rojo', label: 'Rojo', hex: '#DC2626', stock: 1 },
    ],
    badges: ['low_stock'],
  },
  {
    id: 3,
    slug: 'chainsaw-man-t1',
    name: 'Chainsaw Man Tomo 1',
    description: 'Manga tankōbon, edición japonés con sobrecubierta. Idioma: japonés.',
    priceCents: 1900,
    category: 'manga',
    kind: 'manga',
    brand: 'Shueisha',
    finishes: [{ slug: 'estandar', label: 'Estándar', hex: '#EA580C', stock: 24 }],
    badges: ['new'],
  },
  {
    id: 4,
    slug: 'spy-x-family-t1',
    name: 'Spy x Family Tomo 1',
    description: 'Manga tankōbon, edición español. Recomendado 13+.',
    priceCents: 1499,
    category: 'manga',
    kind: 'manga',
    brand: 'Shueisha',
    finishes: [{ slug: 'estandar', label: 'Estándar', hex: '#F59E0B', stock: 30 }],
  },
  {
    id: 5,
    slug: 'jujutsu-kaisen-hoodie',
    name: 'Hoodie Jujutsu Kaisen',
    description: 'Sudadera con capucha negra, logo Gojo bordado. 80% algodón, 20% polyester.',
    priceCents: 4900,
    category: 'apparel',
    kind: 'hoodie',
    brand: 'MAPPA',
    finishes: [
      { slug: 'negro', label: 'Negro', hex: '#1C1917', stock: 6 },
      { slug: 'morado', label: 'Morado', hex: '#7C3AED', stock: 6 },
    ],
  },
  {
    id: 6,
    slug: 'totoro-plush',
    name: 'Peluche Totoro mediano',
    description: 'Peluche de Totoro (Mi Vecino Totoro). 30cm, suave al tacto. Edición oficial.',
    priceCents: 3500,
    category: 'accessories',
    kind: 'plush',
    brand: 'Studio Ghibli',
    finishes: [
      { slug: 'gris', label: 'Gris', hex: '#6B7280', stock: 9 },
      { slug: 'azul-cielo', label: 'Azul cielo', hex: '#2563EB', stock: 9 },
    ],
    badges: ['new'],
  },
  {
    id: 7,
    slug: 'evangelion-unit-01',
    name: 'Evangelion Unit-01 1/6',
    description:
      'Figura escala 1/6 del Eva-01. 30cm, piezas articuladas, base con luz LED opcional.',
    priceCents: 22500,
    compareAtPriceCents: 24900,
    category: 'figures',
    kind: 'figure',
    brand: 'Kotobukiya',
    finishes: [
      { slug: 'violeta', label: 'Violeta', hex: '#7C3AED', stock: 1 },
      { slug: 'verde-neon', label: 'Verde neón', hex: '#84CC16', stock: 1 },
    ],
    badges: ['sale', 'low_stock'],
  },
  {
    id: 8,
    slug: 'demon-slayer-keychain',
    name: 'Llavero Demon Slayer',
    description: 'Llavero metálico con el logo del cuerpo de cazadores. 4cm.',
    priceCents: 800,
    category: 'accessories',
    kind: 'keychain',
    brand: 'Bandai',
    finishes: [
      { slug: 'dorado', label: 'Dorado', hex: '#CA8A04', stock: 23 },
      { slug: 'negro', label: 'Negro', hex: '#1C1917', stock: 22 },
    ],
  },
  {
    id: 9,
    slug: 'one-piece-vol-1',
    name: 'One Piece Tomo 1',
    description: 'Reedición aniversario del manga. Sobrecubierta con efecto metálico.',
    priceCents: 2100,
    category: 'manga',
    kind: 'manga',
    brand: 'Shueisha',
    finishes: [{ slug: 'estandar', label: 'Estándar', hex: '#DC2626', stock: 22 }],
  },
  {
    id: 10,
    slug: 'attack-on-titan-cap',
    name: 'Gorra Attack on Titan',
    description: 'Gorra negra con bordado del emblema Scout Regiment. Ajustable.',
    priceCents: 2200,
    category: 'apparel',
    kind: 'cap',
    brand: 'Kodansha',
    finishes: [
      { slug: 'negro', label: 'Negro', hex: '#1C1917', stock: 8 },
      { slug: 'verde', label: 'Verde', hex: '#166534', stock: 8 },
    ],
  },
  {
    id: 11,
    slug: 'kakashi-naruto-plush',
    name: 'Peluche Kakashi Hatake',
    description: 'Peluche del Copy Ninja. 25cm, máscara y bandana incluidas.',
    priceCents: 2900,
    category: 'accessories',
    kind: 'plush',
    brand: 'Bandai',
    finishes: [
      { slug: 'verde-konoha', label: 'Verde Konoha', hex: '#16A34A', stock: 3 },
      { slug: 'gris', label: 'Gris', hex: '#6B7280', stock: 2 },
    ],
    badges: ['low_stock'],
  },
  {
    id: 12,
    slug: 'studio-ghibli-mug',
    name: 'Taza Studio Ghibli',
    description: 'Taza de cerámica 350ml. Estampado Totoro/No-Face a elección.',
    priceCents: 1800,
    category: 'accessories',
    kind: 'mug',
    brand: 'Studio Ghibli',
    finishes: [
      { slug: 'blanco', label: 'Blanco', hex: '#F7F4EF', stock: 10 },
      { slug: 'negro', label: 'Negro', hex: '#1C1917', stock: 10 },
    ],
    badges: ['new'],
  },
];

export const PRODUCTS: readonly Product[] = createCatalogProducts(KITSUNE_SEEDS);

/**
 * Helper: devuelve los productos destacados para la home (los primeros 4
 * con stock > 0, priorizando los que tienen badges).
 */
export function getFeaturedProducts(limit = 4): readonly Product[] {
  return [...PRODUCTS]
    .filter((product) => product.stock > 0)
    .sort((a, b) => (b.badges?.length ?? 0) - (a.badges?.length ?? 0))
    .slice(0, limit);
}

/**
 * Helper: busca un producto por slug. Devuelve `null` si no existe.
 * Usado por la página de detalle `/[locale]/products/[slug].astro`.
 */
export function findProductBySlug(slug: string): Product | null {
  return ALL_PRODUCTS.find((product) => product.slug === slug) ?? null;
}

/**
 * Catálogo unificado de demo: incluye productos de La Voz Instrumentos
 * (guitarras de GUITAR_SPECS) además de los productos Kitsune hardcoded.
 *
 * Dev mode: la PDP (`[slug].astro`) hace lookup contra este array, así
 * que ambos tenants del dev environment son navegables. En producción
 * cada tenant tiene su propio PRODUCTS via API.
 */
const LA_VOZ_AS_PRODUCTS: readonly Product[] = LA_VOZ_PRODUCTS.map(laVozProductToProduct);

const ALL_PRODUCTS: readonly Product[] = [...PRODUCTS, ...LA_VOZ_AS_PRODUCTS];

/**
 * Helper: filtra productos por categoría. Devuelve `[]` si no hay matches.
 */
export function findProductsByCategory(categorySlug: string): readonly Product[] {
  return PRODUCTS.filter((product) => product.category === categorySlug);
}

/**
 * Helper: devuelve todas las categorías únicas presentes en PRODUCTS.
 * Útil para construir la barra de filtros de la página /products.
 */
export function getCategoriesInUse(): readonly Category[] {
  const slugs = new Set(PRODUCTS.map((product) => product.category));
  return CATEGORIES.filter((category) => slugs.has(category.slug));
}

