/**
 * Catálogo de prototipo de Panda Estampados.
 *
 * Ocho piezas de estampado, como máximo dos colores. La API del tenant
 * todavía no tiene productos; home, catálogo, ficha, carrito y checkout
 * leen este módulo cuando el host es Panda y el backend responde vacío.
 * Kitsune y La Voz no lo usan.
 */
import type { Product, ProductImage, ProductVariant } from '../types/catalog';
import { getBrandBySlug } from '../config/stores';

export const PANDA_TENANT_SLUG = 'panda-estampados';

/** Panda, o el host sin marca (la marca por defecto del storefront). */
export function isPandaStore(tenantSlug: string | null): boolean {
  return getBrandBySlug(tenantSlug).slug === PANDA_TENANT_SLUG;
}

const PAPER = '#F3EFE8';
const DARK_FILLS = new Set(['#1C1917', '#1E3A5F', '#8A8580']);

export const PANDA_CATEGORIES = [
  { slug: 'textil', label: 'Textil' },
  { slug: 'drinkware', label: 'Drinkware' },
  { slug: 'accesorio', label: 'Accesorio' },
] as const;

type GarmentKind = 'polo' | 'shirt' | 'hoodie' | 'mug' | 'tote' | 'cap' | 'pen' | 'notebook';

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
  category: (typeof PANDA_CATEGORIES)[number]['slug'];
  kind: GarmentKind;
  finishes: readonly PandaFinish[];
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
    category: 'drinkware',
    kind: 'mug',
    finishes: [
      { slug: 'blanco', label: 'Blanco', hex: '#F7F4EF' },
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
    ],
  },
  {
    id: 9105,
    slug: 'tote',
    name: 'Tote',
    description: 'Bolsa de lona con asas largas. Un estampado por cara.',
    priceCents: 1500,
    category: 'accesorio',
    kind: 'tote',
    finishes: [
      { slug: 'crudo', label: 'Crudo', hex: '#E7D7C1' },
      { slug: 'negro', label: 'Negro', hex: '#1C1917' },
    ],
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
  },
];

/** Líneas de demostración compartidas por carrito y checkout. */
export const PANDA_DEMO_LINES = [
  { productSlug: 'polo-pique', quantity: 12 },
  { productSlug: 'taza-11-oz', quantity: 24 },
] as const;

const GARMENT_PATHS: Record<GarmentKind, string> = {
  polo: 'M78 72 L98 52 Q120 70 142 52 L162 72 L152 104 L142 96 L142 248 L98 248 L98 96 L88 104 Z',
  shirt: 'M72 78 L96 56 Q120 74 144 56 L168 78 L156 108 L146 100 L146 250 L94 250 L94 100 L84 108 Z',
  hoodie: 'M70 86 Q90 48 108 70 Q120 52 132 70 Q150 48 170 86 L158 112 L146 102 L146 250 L94 250 L94 102 L82 112 Z',
  mug: 'M86 78 L86 230 Q86 246 102 246 L150 246 Q166 246 166 230 L166 78 Z M166 108 Q198 108 198 148 Q198 188 166 188',
  tote: 'M78 108 L78 246 L162 246 L162 108 Z M96 108 Q96 72 120 72 Q144 72 144 108',
  cap: 'M52 168 L188 168 L176 156 Q168 96 120 96 Q72 96 64 156 Z',
  pen: 'M108 48 L132 48 L128 236 Q120 250 112 236 Z',
  notebook: 'M72 64 L156 64 L168 248 L84 248 Z',
};

function markFill(garmentHex: string): string {
  return DARK_FILLS.has(garmentHex.toUpperCase()) ? PAPER : '#1C6B63';
}

function previewUrl(kind: GarmentKind, garmentHex: string): string {
  const svg = [
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 300">`,
    `<rect width="240" height="300" fill="${PAPER}"/>`,
    `<path d="${GARMENT_PATHS[kind]}" fill="${garmentHex}" stroke="#1C1917" stroke-width="2" stroke-linejoin="round"/>`,
    `<text x="120" y="168" text-anchor="middle" font-family="Georgia,serif" font-size="16" fill="${markFill(garmentHex)}">Panda</text>`,
    `</svg>`,
  ].join('');
  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

function toVariant(piece: PandaPiece, finish: PandaFinish, index: number): ProductVariant {
  return {
    id: `${piece.id}-${index + 1}`,
    name: finish.label,
    finishSlug: finish.slug,
    finishLabel: finish.label,
    finishHex: finish.hex,
    condition: 'nuevo',
    stock: 40,
    handedness: null,
  };
}

function toImage(piece: PandaPiece, finish: PandaFinish, variantId: string, index: number): ProductImage {
  return {
    url: previewUrl(piece.kind, finish.hex),
    altText: `${piece.name}, ${finish.label}`,
    isPrimary: index === 0,
    isHover: false,
    variantId,
    sortOrder: index,
    srcset: '',
  };
}

function toProduct(piece: PandaPiece): Product {
  const variants = piece.finishes.map((finish, index) => toVariant(piece, finish, index));
  const images = piece.finishes.map((finish, index) =>
    toImage(piece, finish, variants[index]?.id ?? '', index),
  );
  const cover = images[0];
  return {
    id: piece.id,
    slug: piece.slug,
    name: piece.name,
    description: piece.description,
    priceCents: piece.priceCents,
    imageUrl: cover?.url ?? '',
    images,
    variants,
    category: piece.category,
    brand: '',
    stock: 40,
    currency: 'USD',
  };
}

export const PANDA_PRODUCTS: readonly Product[] = PIECES.map(toProduct);

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
