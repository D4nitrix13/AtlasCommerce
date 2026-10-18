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
 *  - Imágenes: placeholders SVG inline (data URI) — no hace falta
 *    asset binario y el bundle funciona offline. TODO: reemplazar
 *    por assets reales en F4 cuando exista CDN de storefront.
 *  - Bilingüe (nameEs / nameEn): refleja cómo el backend probablemente
 *    devolverá traducciones en columnas separadas. El consumer elige
 *    según locale.
 */
import type { Category, Product } from '../types/catalog';
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
    // Gallery completa (real para La Voz, placeholder para Kitsune que
    // no tienen gallery). Sin esta línea el PDP cae al fallback de
    // SABER_ALTER_DETAIL.images (4 T-shirts mockup).
    images: lv.images,
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

export const PRODUCTS: readonly Product[] = [
  {
    id: 1,
    slug: 'saber-alter-figma',
    name: 'Saber Alter Figma',
    description:
      'Figma articulada de Saber Alter (Fate/Stay Night). 14cm, incluye base y manos intercambiables.',
    priceCents: 14500,
    compareAtPriceCents: 16900,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'figures',
    stock: 8,
    badges: ['new', 'sale'],
    currency: 'USD',
  },
  {
    id: 2,
    slug: 'nendoroid-zero-two',
    name: 'Nendoroid Zero Two',
    description: 'Nendoroid de Zero Two (Darling in the Franxx). 10cm, tres expresiones faciales.',
    priceCents: 7900,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'figures',
    stock: 3,
    badges: ['low_stock'],
    currency: 'USD',
  },
  {
    id: 3,
    slug: 'chainsaw-man-t1',
    name: 'Chainsaw Man Tomo 1',
    description: 'Manga tankōbon, edición japonés con sobrecubierta. Idioma: japonés.',
    priceCents: 1900,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'manga',
    stock: 24,
    badges: ['new'],
    currency: 'USD',
  },
  {
    id: 4,
    slug: 'spy-x-family-t1',
    name: 'Spy x Family Tomo 1',
    description: 'Manga tankōbon, edición español. Recomendado 13+.',
    priceCents: 1499,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'manga',
    stock: 30,
    currency: 'USD',
  },
  {
    id: 5,
    slug: 'jujutsu-kaisen-hoodie',
    name: 'Hoodie Jujutsu Kaisen',
    description: 'Sudadera con capucha negra, logo Gojo bordado. 80% algodón, 20% polyester.',
    priceCents: 4900,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'apparel',
    stock: 12,
    currency: 'USD',
  },
  {
    id: 6,
    slug: 'totoro-plush',
    name: 'Peluche Totoro mediano',
    description: 'Peluche de Totoro (Mi Vecino Totoro). 30cm, suave al tacto. Edición oficial.',
    priceCents: 3500,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'accessories',
    stock: 18,
    badges: ['new'],
    currency: 'USD',
  },
  {
    id: 7,
    slug: 'evangelion-unit-01',
    name: 'Evangelion Unit-01 1/6',
    description:
      'Figura escala 1/6 del Eva-01. 30cm, piezas articuladas, base con luz LED opcional.',
    priceCents: 22500,
    compareAtPriceCents: 24900,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'figures',
    stock: 2,
    badges: ['sale', 'low_stock'],
    currency: 'USD',
  },
  {
    id: 8,
    slug: 'demon-slayer-keychain',
    name: 'Llavero Demon Slayer',
    description: 'Llavero metálico con el logo del cuerpo de cazadores. 4cm.',
    priceCents: 800,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'accessories',
    stock: 45,
    currency: 'USD',
  },
  {
    id: 9,
    slug: 'one-piece-vol-1',
    name: 'One Piece Tomo 1',
    description: 'Reedición aniversario del manga. Sobrecubierta con efecto metálico.',
    priceCents: 2100,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'manga',
    stock: 22,
    currency: 'USD',
  },
  {
    id: 10,
    slug: 'attack-on-titan-cap',
    name: 'Gorra Attack on Titan',
    description: 'Gorra negra con bordado del emblema Scout Regiment. Ajustable.',
    priceCents: 2200,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'apparel',
    stock: 16,
    currency: 'USD',
  },
  {
    id: 11,
    slug: 'kakashi-naruto-plush',
    name: 'Peluche Kakashi Hatake',
    description: 'Peluche del Copy Ninja. 25cm, máscara y bandana incluidas.',
    priceCents: 2900,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'accessories',
    stock: 5,
    badges: ['low_stock'],
    currency: 'USD',
  },
  {
    id: 12,
    slug: 'studio-ghibli-mug',
    name: 'Taza Studio Ghibli',
    description: 'Taza de cerámica 350ml. Estampado Totoro/No-Face a elección.',
    priceCents: 1800,
    imageUrl: PLACEHOLDER_IMAGE,
    category: 'accessories',
    stock: 20,
    badges: ['new'],
    currency: 'USD',
  },
] as const;

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

