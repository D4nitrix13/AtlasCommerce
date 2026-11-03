/**
 * Catálogo de prototipo de Panda Estampados.
 *
 * Ocho piezas de estampado, como máximo dos colores. La API del tenant
 * todavía no tiene productos; home, catálogo, ficha, carrito y checkout
 * leen este módulo cuando el host es Panda y el backend responde vacío.
 * Kitsune y La Voz no lo usan.
 *
 * Sale de `lib/catalog-product-factory`: cada pieza es un `ProductSeed`
 * y la factory arma la galería front+back por acabado, variants y stock.
 * Badges/compareAt con criterio de escaparate:
 *   - `sale` + precio tachado en taza y tote (las dos piezas de mayor
 *     rotación de demo, una de drinkware y una de accesorio).
 *   - `new` en libreta y gorra (lo más reciente del catálogo).
 */
import type { Product } from '../types/catalog';
import { getBrandBySlug } from '../config/stores';
import {
  createCatalogProducts,
  type CatalogSilhouetteKind,
  type ProductSeed,
} from '../lib/catalog-product-factory';

export const PANDA_TENANT_SLUG = 'panda-estampados';

/** Panda, o el host sin marca (la marca por defecto del storefront). */
export function isPandaStore(tenantSlug: string | null): boolean {
  return getBrandBySlug(tenantSlug).slug === PANDA_TENANT_SLUG;
}

export const PANDA_CATEGORIES = [
  { slug: 'textil', label: 'Textil' },
  { slug: 'drinkware', label: 'Drinkware' },
  { slug: 'accesorio', label: 'Accesorio' },
] as const;

type GarmentKind = Extract<
  CatalogSilhouetteKind,
  'polo' | 'shirt' | 'hoodie' | 'mug' | 'tote' | 'cap' | 'pen' | 'notebook'
>;

interface PandaFinish {
  slug: string;
  label: string;
  hex: string;
}

interface PandaPiece {
  id: number;
  slug: string;
  name: string;
  description: string;
  priceCents: number;
  compareAtPriceCents?: number;
  category: (typeof PANDA_CATEGORIES)[number]['slug'];
  kind: GarmentKind;
  finishes: readonly PandaFinish[];
  badges?: ProductSeed['badges'];
}

const PIECES: readonly PandaPiece[] = [
  {
    id: 9101,
    slug: 'polo-pique',
    name: 'Polo piqué',
    description: 'Polo de piqué para uniforme. Estampado a un color en el pecho.',
    priceCents: 2400,
    category: 'textil',
    kind: 'polo',
    finishes: [
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
      { slug: 'crema', label: 'Crema', hex: '#F3EDE4' },
    ],
  },
  {
    id: 9102,
    slug: 'playera',
    name: 'Playera',
    description: 'Playera de algodón para evento o equipo. Cuello redondo.',
    priceCents: 1800,
    category: 'textil',
    kind: 'shirt',
    finishes: [
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
      { slug: 'blanco', label: 'Blanco', hex: '#F7F4EF' },
    ],
  },
  {
    id: 9103,
    slug: 'hoodie',
    name: 'Hoodie',
    description: 'Sudadera con capucha. Estampado centrado, sin relieve.',
    priceCents: 4200,
    category: 'textil',
    kind: 'hoodie',
    finishes: [
      { slug: 'marino', label: 'Marino', hex: '#1E3A5F' },
      { slug: 'gris', label: 'Gris', hex: '#8A8580' },
    ],
  },
  {
    id: 9104,
    slug: 'taza-11-oz',
    name: 'Taza 11 oz',
    description: 'Taza de cerámica de 11 oz. El estampado envuelve el frente.',
    priceCents: 1200,
    compareAtPriceCents: 1500,
    category: 'drinkware',
    kind: 'mug',
    finishes: [
      { slug: 'blanco', label: 'Blanco', hex: '#F7F4EF' },
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
    ],
    badges: ['sale'],
  },
  {
    id: 9105,
    slug: 'tote',
    name: 'Tote',
    description: 'Bolsa de lona con asas largas. Un estampado por cara.',
    priceCents: 1500,
    compareAtPriceCents: 1800,
    category: 'accesorio',
    kind: 'tote',
    finishes: [
      { slug: 'crudo', label: 'Crudo', hex: '#E7D7C1' },
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
    ],
    badges: ['sale'],
  },
  {
    id: 9106,
    slug: 'gorra',
    name: 'Gorra',
    description: 'Gorra de seis paneles. Bordado o estampado en el frente.',
    priceCents: 1600,
    category: 'accesorio',
    kind: 'cap',
    finishes: [
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
      { slug: 'marino', label: 'Marino', hex: '#1E3A5F' },
    ],
    badges: ['new'],
  },
  {
    id: 9107,
    slug: 'boligrafo',
    name: 'Bolígrafo',
    description: 'Bolígrafo plástico con logo en el cuerpo. Un solo color.',
    priceCents: 400,
    category: 'accesorio',
    kind: 'pen',
    finishes: [{ slug: 'negro', label: 'Negro', hex: '#1C1917' }],
  },
  {
    id: 9108,
    slug: 'libreta',
    name: 'Libreta',
    description: 'Libreta kraft con tapa blanda. Logo en la portada.',
    priceCents: 900,
    category: 'accesorio',
    kind: 'notebook',
    finishes: [{ slug: 'kraft', label: 'Kraft', hex: '#C4A574' }],
    badges: ['new'],
  },
];

/** Líneas de demostración compartidas por carrito y checkout. */
export const PANDA_DEMO_LINES = [
  { productSlug: 'polo-pique', quantity: 12 },
  { productSlug: 'taza-11-oz', quantity: 24 },
] as const;

/**
 * Stock por acabado: 20 en piezas de dos colores y 40 en las de un
 * solo color, para que el total por producto siga siendo 40 como en el
 * mock anterior.
 */
function toSeed(piece: PandaPiece): ProductSeed {
  const perFinishStock = 40 / piece.finishes.length;
  return {
    id: piece.id,
    slug: piece.slug,
    name: piece.name,
    description: piece.description,
    priceCents: piece.priceCents,
    category: piece.category,
    kind: piece.kind,
    brand: 'Panda Estampados',
    finishes: piece.finishes.map((finish) => ({ ...finish, stock: perFinishStock })),
    ...(piece.badges !== undefined && { badges: piece.badges }),
    ...(piece.compareAtPriceCents !== undefined && {
      compareAtPriceCents: piece.compareAtPriceCents,
    }),
  };
}

export const PANDA_PRODUCTS: readonly Product[] = createCatalogProducts(PIECES.map(toSeed));

export function findPandaProduct(slug: string): Product | null {
  return PANDA_PRODUCTS.find((product) => product.slug === slug) ?? null;
}

export function getPandaFeaturedProducts(limit = 4): readonly Product[] {
  return PANDA_PRODUCTS.slice(0, limit);
}

export function pandaCategoryLabel(slug: string): string {
  return PANDA_CATEGORIES.find((category) => category.slug === slug)?.label ?? slug;
}

export interface PandaColorOption {
  slug: string;
  label: string;
  hex: string;
  count: number;
}

export function pandaColorOptions(): readonly PandaColorOption[] {
  const bySlug = new Map<string, PandaColorOption>();
  for (const product of PANDA_PRODUCTS) {
    for (const variant of product.variants ?? []) {
      const slug = variant.finishSlug;
      const hex = variant.finishHex;
      if (slug === undefined || hex === null || hex === undefined) continue;
      const current = bySlug.get(slug);
      if (current === undefined) {
        bySlug.set(slug, { slug, label: variant.finishLabel ?? slug, hex, count: 1 });
      } else {
        current.count += 1;
      }
    }
  }
  return [...bySlug.values()];
}
