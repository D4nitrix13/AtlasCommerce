/**
 * catalog-product-factory — construye `Product` en la shape rica para
 * tenants sin fotos reales de producto (Panda, Kitsune).
 *
 * Mismo molde que La Voz (front + back por variante, `isHover`,
 * swatches por `finishHex`), pero con imágenes SVG generadas en vez de
 * webp: `assets/products/<tenant>/` está vacío y no hay CDN. La factory
 * es pura y agnóstica al tenant; cada catálogo le pasa sus seeds.
 *
 * Contrato de galería (espeja `product-card-swap.ts`):
 *   - 2 imágenes por variante: front (`isPrimary` sólo en la primera
 *     variante) y back.
 *   - Exactamente UNA foto de hover por producto (contrato del backend
 *     en `ProductImage.isHover`): la back de la primera variante. Las
 *     backs restantes son galería para el PDP, sin el flag.
 *   - `variantId` siempre apunta a la variante dueña de la foto.
 */
import type {
  Product,
  ProductBadge,
  ProductImage,
  ProductSize,
  ProductVariant,
} from '../types/catalog';

/** Alias que el seed puede usar además del vocabulario canónico. */
export type ProductBadgeSeed = ProductBadge | 'lowStock';

/**
 * Acabado de un producto. `stock` es por variante; si se omite usa
 * `DEFAULT_VARIANT_STOCK` y el stock total del producto es la suma.
 */
export interface CatalogFinishSeed {
  slug: string;
  label: string;
  hex: string;
  stock?: number;
  condition?: string;
}

/**
 * Siluetas que la factory sabe dibujar en el SVG generado. Cada tenant
 * tipa sus piezas con el `kind` que mejor las represente.
 */
export type CatalogSilhouetteKind =
  | 'polo'
  | 'shirt'
  | 'hoodie'
  | 'mug'
  | 'tote'
  | 'cap'
  | 'pen'
  | 'notebook'
  | 'figure'
  | 'manga'
  | 'plush'
  | 'keychain';

export interface ProductSeed {
  id: number;
  slug: string;
  name: string;
  description: string;
  priceCents: number;
  category: string;
  kind: CatalogSilhouetteKind;
  brand: string;
  finishes: readonly CatalogFinishSeed[];
  badges?: readonly ProductBadgeSeed[];
  compareAtPriceCents?: number;
  /** Stock total explícito; si se omite, suma el stock de las variants. */
  stock?: number;
  isExclusive?: boolean;
  sizes?: readonly ProductSize[];
}

export type ImageRole = 'front' | 'back';

const DEFAULT_VARIANT_STOCK = 24;

const BADGE_ALIASES: Record<string, ProductBadge> = {
  new: 'new',
  sale: 'sale',
  low_stock: 'low_stock',
  lowstock: 'low_stock',
};

function normalizeBadges(badges: readonly ProductBadgeSeed[] | undefined): ProductBadge[] {
  if (badges === undefined) return [];
  const normalized: ProductBadge[] = [];
  for (const badge of badges) {
    const canonical = BADGE_ALIASES[badge.toLowerCase()];
    if (canonical !== undefined && !normalized.includes(canonical)) {
      normalized.push(canonical);
    }
  }
  return normalized;
}

// ─── SVG generado ─────────────────────────────────────────────────────

const PAPER = '#F3EFE8';
const STROKE = '#1C1917';

/**
 * Marca estampada sobre la pieza. Usa luminancia en vez de una lista de
 * hexes oscuros: el finish lo pone cada tenant y no se puede enumerar.
 */
function markFill(finishHex: string): string {
  const hex = finishHex.replace('#', '');
  const full = hex.length === 3 ? hex.replace(/./g, (c) => c + c) : hex;
  const r = parseInt(full.slice(0, 2), 16);
  const g = parseInt(full.slice(2, 4), 16);
  const b = parseInt(full.slice(4, 6), 16);
  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
  return luminance < 140 ? PAPER : '#1C6B63';
}

/** Marca de cada silueta dentro del viewBox 240×300. */
const SILHOUETTES: Record<CatalogSilhouetteKind, string> = {
  polo: '<path d="M78 72 L98 52 Q120 70 142 52 L162 72 L152 104 L142 96 L142 248 L98 248 L98 96 L88 104 Z"/>',
  shirt: '<path d="M72 78 L96 56 Q120 74 144 56 L168 78 L156 108 L146 100 L146 250 L94 250 L94 100 L84 108 Z"/>',
  hoodie:
    '<path d="M70 86 Q90 48 108 70 Q120 52 132 70 Q150 48 170 86 L158 112 L146 102 L146 250 L94 250 L94 102 L82 112 Z"/>',
  mug: '<path d="M86 78 L86 230 Q86 246 102 246 L150 246 Q166 246 166 230 L166 78 Z"/><path d="M166 108 Q198 108 198 148 Q198 188 166 188" fill="none"/>',
  tote: '<path d="M78 108 L78 246 L162 246 L162 108 Z"/><path d="M96 108 Q96 72 120 72 Q144 72 144 108" fill="none"/>',
  cap: '<path d="M52 168 L188 168 L176 156 Q168 96 120 96 Q72 96 64 156 Z"/>',
  pen: '<path d="M108 48 L132 48 L128 236 Q120 250 112 236 Z"/>',
  notebook: '<path d="M72 64 L156 64 L168 248 L84 248 Z"/>',
  manga:
    '<path d="M84 56 L164 56 L172 252 L76 252 Z"/><path d="M98 56 L98 252" fill="none"/>',
  figure:
    '<circle cx="120" cy="80" r="30"/><path d="M96 118 L144 118 L154 184 L142 184 L138 252 L102 252 L98 184 L86 184 Z"/>',
  plush:
    '<ellipse cx="86" cy="102" rx="16" ry="36"/><ellipse cx="154" cy="102" rx="16" ry="36"/><circle cx="120" cy="176" r="72"/>',
  keychain:
    '<circle cx="120" cy="64" r="22" fill="none" stroke-width="10"/><path d="M120 86 L120 116" fill="none" stroke-width="8"/><rect x="76" y="116" width="88" height="112" rx="16"/>',
};

/**
 * URL de la imagen generada (SVG data-URI) para un acabado y rol.
 *
 * Front y back se distinguen a propósito: la back va espejada y con una
 * costura discontinua en lugar de la marca estampada, para que el
 * hover-swap se note en la card.
 */
export function buildGeneratedImageUrl(
  seed: ProductSeed,
  finishSlug: string,
  role: ImageRole,
): string {
  const finish = seed.finishes.find((candidate) => candidate.slug === finishSlug);
  if (finish === undefined) {
    throw new Error(`Unknown finish "${finishSlug}" for product "${seed.slug}"`);
  }
  const silhouette = SILHOUETTES[seed.kind];
  const mark = markFill(finish.hex);
  const body =
    role === 'front'
      ? `<g fill="${finish.hex}" stroke="${STROKE}" stroke-width="2" stroke-linejoin="round">${silhouette}</g>` +
        `<circle cx="120" cy="168" r="16" fill="${mark}"/>`
      : `<g transform="translate(240,0) scale(-1,1)" fill="${finish.hex}" stroke="${STROKE}" stroke-width="2" stroke-linejoin="round">${silhouette}</g>` +
        `<path d="M40 168 H200" fill="none" stroke="${mark}" stroke-width="6" stroke-dasharray="12 8"/>`;
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 300">` +
    `<rect width="240" height="300" fill="${PAPER}"/>` +
    body +
    `</svg>`;
  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

// ─── Construcción del Product ─────────────────────────────────────────

function buildVariants(seed: ProductSeed): ProductVariant[] {
  return seed.finishes.map((finish, index) => ({
    id: `${seed.id}-${index + 1}`,
    name: finish.label,
    finishSlug: finish.slug,
    finishLabel: finish.label,
    finishHex: finish.hex,
    condition: finish.condition ?? 'nuevo',
    stock: finish.stock ?? DEFAULT_VARIANT_STOCK,
    handedness: null,
  }));
}

function buildImages(seed: ProductSeed, variants: readonly ProductVariant[]): ProductImage[] {
  const images: ProductImage[] = [];
  for (const [index, variant] of variants.entries()) {
    const finish = seed.finishes[index];
    if (finish === undefined) continue;
    const isPrimaryVariant = index === 0;
    images.push({
      url: buildGeneratedImageUrl(seed, finish.slug, 'front'),
      altText: `${seed.name}, ${finish.label} - vista front`,
      isPrimary: isPrimaryVariant,
      isHover: false,
      variantId: variant.id,
      sortOrder: index * 2,
      srcset: '',
    });
    images.push({
      url: buildGeneratedImageUrl(seed, finish.slug, 'back'),
      altText: `${seed.name}, ${finish.label} - vista back`,
      isPrimary: false,
      // Una sola foto de hover por producto (contrato de
      // `ProductImage.isHover`): la back de la variante primaria.
      isHover: isPrimaryVariant,
      variantId: variant.id,
      sortOrder: index * 2 + 1,
      srcset: '',
    });
  }
  return images;
}

export function createCatalogProduct(seed: ProductSeed): Product {
  const variants = buildVariants(seed);
  const images = buildImages(seed, variants);
  const badges = normalizeBadges(seed.badges);
  const stock =
    seed.stock ?? variants.reduce((total, variant) => total + Number(variant.stock), 0);
  return {
    id: seed.id,
    slug: seed.slug,
    name: seed.name,
    description: seed.description,
    priceCents: seed.priceCents,
    // Precio tachado sólo tiene sentido si el producto está en oferta.
    ...(badges.includes('sale') &&
      seed.compareAtPriceCents !== undefined && {
        compareAtPriceCents: seed.compareAtPriceCents,
      }),
    imageUrl: images[0]?.url ?? '',
    images,
    category: seed.category,
    brand: seed.brand,
    stock,
    ...(badges.length > 0 && { badges }),
    currency: 'USD',
    isExclusive: seed.isExclusive ?? false,
    variants,
    ...(seed.sizes !== undefined && seed.sizes.length > 0 && { sizes: seed.sizes }),
  };
}

export function createCatalogProducts(seeds: readonly ProductSeed[]): readonly Product[] {
  return seeds.map(createCatalogProduct);
}
