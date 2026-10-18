/**
 * product-mock-factory — genera mocks de productos brand-agnostic.
 *
 * Los mocks del storefront público se derivan de `ProductMockSpec`,
 * una shape que coincide con `backend/database/seeds/products.json`
 * (los seeders producen datos equivalentes a este spec). Cuando el
 * endpoint `/api/storefront/products` exista, este factory se reemplaza
 * por fetch + hidratación — la shape se conserva.
 *
 * Helpers:
 *   - `createProductMock(spec)`         → `Product` (card de catálogo)
 *   - `createProductDetailMock(spec)`   → `ProductDetailData` (PDP)
 *   - `createVariantMock(spec, v)`      → `ProductVariant` (galería PDP)
 *   - `createRelatedProduct(spec, v?)`  → `RelatedProduct` (cards "relacionados")
 *
 * Las URLs siguen la misma convención que el `ProductSeeder` del backend:
 *   - "indexed" naming: `{slug}-{variant}-{WxH}-{idx}.webp`
 *   - "keyword" naming: `{slug}-{variant}-{variant}-{front|back}-{WxH}.webp`
 *
 * Path base: `/assets/products/` — coincide con los archivos reales en
 * `frontend/public/assets → ../../assets` (symlink creado en Fase 6
 * del image architecture plan).
 */

import type {
  Product,
  ProductBadge,
  ProductCondition,
  ProductSize,
  ProductVariant as CatalogVariant,
} from '../types/catalog';
import type { ProductHighlightsItem } from '../components/storefront/products/ProductHighlights.types';
import type {
  ProductDetailData,
  ProductVariant,
  ProductVariantAttribute,
  RelatedProduct,
} from '../data/product-detail-mock';

/**
 * Talla declarada en un spec de producto. Misma shape que
 * `ProductSize` en `types/catalog.ts`; alias local para que el
 * spec pueda usarse sin importar el types barrel.
 */
export interface ProductMockSizeSpec {
  /** Slug URL-safe (e.g. '4-4', 's'). */
  readonly slug: string;
  /** Label legible (e.g. '4/4 (Full Size)'). */
  readonly label: string;
  /** Posición en el grid (ascendente). */
  readonly position?: number;
}

/** Spec declarativo de un producto. Compatible con `products.json`. */
export interface ProductMockSpec {
  /** Slug/handle del producto (e.g. 'american-professional-telecaster'). */
  readonly slug: string;
  /** Nombre completo (e.g. 'Fender American Professional Telecaster'). */
  readonly name: string;
  /** Marca legible (e.g. 'Fender'). */
  readonly brand: string;
  /** Slug de la marca (e.g. 'fender'). */
  readonly brandSlug: string;
  /** Nombre legible de la categoría (e.g. 'Guitarra eléctrica'). */
  readonly category: string;
  /** Slug de la categoría (e.g. 'guitarra-electrica'). */
  readonly categorySlug: string;
  /** Descripción detallada. */
  readonly description: string;
  /** Precio base en cents. */
  readonly priceCents: number;
  /** Precio comparativo en cents (opcional). */
  readonly compareAtPriceCents?: number;
  /** Currency code (default 'USD'). */
  readonly currency?: string;
  /** Variantes. */
  readonly variants: ReadonlyArray<ProductMockVariantSpec>;
  /** PDP expone selector "Nuevo / Usado". */
  readonly hasCondition?: boolean;
  /** PDP expone grid de tallas. */
  readonly hasSizes?: boolean;
  /** Stock base (default: variantes * 1). */
  readonly stock?: number;
  /** Badges opcionales. */
  readonly badges?: ReadonlyArray<ProductBadge>;
  /** Patrón de naming de archivos de imagen. */
  readonly imagePattern: ProductImagePatternSpec;
  /** Path base para URLs (default '/assets/products'). */
  readonly imageBasePath?: string;
  /**
   * Bandera "Edición Limitada" (Phase 7). Default `false`. Cuando es
   * `true`, las cards y el PDP muestran badge "Limitado".
   */
  readonly isExclusive?: boolean;
  /**
   * Tallas del producto (Phase 7). Vacío/ausente para guitarras
   * eléctricas y productos sin tallas. Cuando está definido, el
   * PDP renderiza `ProductSizeGrid` con estas opciones.
   */
  readonly sizes?: ReadonlyArray<ProductMockSizeSpec>;
}

export interface ProductMockVariantSpec {
  /** Slug de la variant (e.g. '3-color-sunburst'). */
  readonly slug: string;
  /** Display name legible (e.g. '3-Color Sunburst'). */
  readonly displayName: string;
  /** SKU de la variant. */
  readonly sku: string;
  /** Fingerboard opcional (e.g. 'rosewood' | 'maple'). */
  readonly fingerboard?: 'rosewood' | 'maple';
  /** Back SKU opcional para imágenes compartidas por fingerboard. */
  readonly backSku?: string;
  /** Front image size de esta variant (override del `imagePattern.frontSize`). */
  readonly frontSize?: string;
  /** Back image size de esta variant (override del `imagePattern.backSize`). */
  readonly backSize?: string;
  /** Front image index de esta variant (override del `imagePattern.frontIndex`). */
  readonly frontIndex?: string;
  /** Back image index de esta variant (override del `imagePattern.backIndex`). */
  readonly backIndex?: string;
  /** Si esta variant es la default para mostrar. */
  readonly isDefault?: boolean;
  /** Hex color para swatch (e.g. '#3B241C'). */
  readonly hex?: string;
  /** Stock de la variant (default: 5). */
  readonly stock?: number;
  /**
   * Slug del acabado (Phase 7). Típicamente igual a `slug`, pero
   * mantenemos el campo separado para mapping con el backend
   * (`product_variants.finish_slug`).
   */
  readonly finishSlug?: string;
  /** Label legible del acabado (Phase 7). Backend: `finish_label`. */
  readonly finishLabel?: string;
  /** Condición de la variant (Phase 7). Backend: `product_variants.condition`. */
  readonly condition?: ProductCondition;
}

export type ProductImagePatternSpec =
  | {
      readonly naming: 'indexed';
      readonly frontSize: string;
      readonly backSize: string;
      readonly frontIndex: string;
      readonly backIndex: string;
    }
  | {
      readonly naming: 'keyword';
      readonly frontSize: string;
      readonly backSize: string;
      readonly frontKeyword: string;
      readonly backKeyword: string;
      readonly frontIndex?: string;
      readonly backIndex?: string;
    };

const DEFAULT_BASE_PATH = '/assets/products';
const DEFAULT_STOCK = 5;
const DEFAULT_VARIANT_STOCK = 5;

/**
 * Resuelve la variant "default" para mostrar (la marcada con
 * `is_default: true` o la primera del array).
 */
function defaultVariant<T extends ProductMockVariantSpec>(
  variants: ReadonlyArray<T>,
): T {
  return variants.find((variant) => variant.isDefault === true) ?? variants[0]!;
}

/**
 * Genera la URL pública de una imagen según el patrón del producto.
 */
export function buildImageUrl(
  spec: ProductMockSpec,
  variantSlug: string,
  role: 'front' | 'back',
  backSku?: string,
): string {
  const basePath = spec.imageBasePath ?? DEFAULT_BASE_PATH;
  const pattern = spec.imagePattern;
  const variant = spec.variants.find((v) => v.slug === variantSlug);
  const variantFrontSize = variant?.frontSize ?? pattern.frontSize;
  const variantBackSize = variant?.backSize ?? pattern.backSize;
  const variantFrontIndex = variant?.frontIndex ?? pattern.frontIndex;
  const variantBackIndex = variant?.backIndex ?? pattern.backIndex;
  const brandPrefix = spec.slug.startsWith(spec.brandSlug) ? '' : `${spec.brandSlug}-`;

  if (pattern.naming === 'indexed') {
    const size = role === 'front' ? variantFrontSize : variantBackSize;
    const index = role === 'front' ? variantFrontIndex : variantBackIndex;
    const useBackSku = role === 'back' && backSku !== undefined;

    const filename = useBackSku
      ? `${backSku}-${backSku}-${size}-${index}`
      : `${brandPrefix}${spec.slug}-${variantSlug}-${size}-${index}`;

    return `${basePath}/${spec.slug}/${variantSlug}/${filename}.webp`;
  }

  // naming === 'keyword' (Gibson Mod Collection). El handle ya
  // termina con el variant en este catálogo (e.g. `...-holy-shift`),
  // así que el filename queda como `{slug}-{variant}-{key}-{WxH}` —
  // el variant aparece naturalmente 2x sin necesidad de duplicarlo.
  const size = role === 'front' ? variantFrontSize : variantBackSize;
  const keyword = role === 'front' ? pattern.frontKeyword : pattern.backKeyword;
  const filename = `${brandPrefix}${spec.slug}-${variantSlug}-${keyword}-${size}`;

  return `${basePath}/${spec.slug}/${variantSlug}/${filename}.webp`;
}

/**
 * Crea un `Product` (card de catálogo) a partir del spec.
 *
 * Usa la variant default para la imagen principal.
 */
export function createProductMock(
  spec: ProductMockSpec,
  options: { readonly id?: number } = {},
): Product {
  const defaultV = defaultVariant(spec.variants);
  const stock = spec.stock ?? spec.variants.length * DEFAULT_STOCK;
  const id = options.id ?? stableIdFromSlug(spec.slug);
  const variants: readonly CatalogVariant[] = spec.variants.map((variant) =>
    specVariantToCatalogVariant(spec, variant),
  );
  const sizes: readonly ProductSize[] | undefined =
    spec.sizes !== undefined && spec.sizes.length > 0
      ? spec.sizes.map((size) => ({
          slug: size.slug,
          label: size.label,
          ...(size.position !== undefined && { position: size.position }),
        }))
      : undefined;

  return {
    id,
    slug: spec.slug,
    name: spec.name,
    description: spec.description,
    priceCents: spec.priceCents,
    ...(spec.compareAtPriceCents !== undefined && {
      compareAtPriceCents: spec.compareAtPriceCents,
    }),
    imageUrl: buildImageUrl(spec, defaultV.slug, 'front', defaultV.backSku),
    category: spec.categorySlug,
    stock,
    ...(spec.badges !== undefined && { badges: [...spec.badges] }),
    currency: spec.currency ?? 'USD',
    // Gallery: front image por variant. Se usa en el PDP para mostrar
    // imágenes reales en lugar del placeholder del mock genérico.
    images: spec.variants.map((variant) =>
      buildImageUrl(spec, variant.slug, 'front', variant.backSku),
    ),
    ...(spec.isExclusive === true && { isExclusive: true }),
    variants,
    ...(sizes !== undefined && { sizes }),
  };
}

/**
 * Crea un `ProductVariant` (entry de la galería PDP) desde un
 * spec de variant.
 */
export function createVariantMock(
  spec: ProductMockSpec,
  variant: ProductMockVariantSpec,
): ProductVariant {
  const fingerboardAttr: ProductVariantAttribute | null =
    variant.fingerboard !== undefined
      ? {
          type: 'color',
          id: variant.slug,
          label: variant.displayName,
          hex: variant.hex,
        }
      : null;

  return {
    id: variant.slug,
    sku: variant.sku,
    attributes: fingerboardAttr !== null ? [fingerboardAttr] : [],
    priceCents: spec.priceCents,
    imageUrl: buildImageUrl(spec, variant.slug, 'front', variant.backSku),
    stock: variant.stock ?? DEFAULT_VARIANT_STOCK,
    ...(variant.condition !== undefined && { condition: variant.condition }),
    ...(variant.finishSlug !== undefined && { finishSlug: variant.finishSlug }),
    ...(variant.finishLabel !== undefined && { finishLabel: variant.finishLabel }),
  };
}

/**
 * Crea un `ProductDetailData` (página de detalle) a partir del spec.
 *
 * La galería incluye las 2 imágenes por variant (front + back).
 * `specs`/`groupedSpecs`/`highlights` quedan vacíos por defecto;
 * los callers pueden sobreescribirlos con un wrapper si necesitan
 * contenido específico.
 */
export function createProductDetailMock(
  spec: ProductMockSpec,
  options: { readonly id?: number } = {},
): ProductDetailData {
  const stock = spec.stock ?? spec.variants.length * DEFAULT_STOCK;
  const id = options.id ?? stableIdFromSlug(spec.slug);

  // Construir galería: para cada variant, su front + back.
  const images: string[] = [];
  for (const variant of spec.variants) {
    images.push(buildImageUrl(spec, variant.slug, 'front', variant.backSku));
    images.push(buildImageUrl(spec, variant.slug, 'back', variant.backSku));
  }

  const variantAttributes: ProductVariantAttribute[][] = spec.variants.map((variant) => [
    {
      type: 'color',
      id: variant.slug,
      label: variant.displayName,
      ...(variant.hex !== undefined && { hex: variant.hex }),
    },
  ]);

  const sizes: ReadonlyArray<{ slug: string; label: string; position?: number }> | undefined =
    spec.sizes !== undefined && spec.sizes.length > 0
      ? spec.sizes.map((size) => ({
          slug: size.slug,
          label: size.label,
          ...(size.position !== undefined && { position: size.position }),
        }))
      : undefined;

  return {
    id,
    slug: spec.slug,
    name: spec.name,
    description: spec.description,
    priceCents: spec.priceCents,
    ...(spec.compareAtPriceCents !== undefined && {
      compareAtPriceCents: spec.compareAtPriceCents,
    }),
    currency: spec.currency ?? 'USD',
    category: spec.categorySlug,
    stock,
    badges: [...(spec.badges ?? [])],
    hasCondition: spec.hasCondition ?? false,
    hasSizes: spec.hasSizes ?? false,
    ...(spec.isExclusive === true && { isExclusive: true }),
    ...(sizes !== undefined && { sizes }),
    images,
    variantAttributes,
    variants: spec.variants.map((variant) => createVariantMock(spec, variant)),
    specs: defaultSpecs(spec),
    groupedSpecs: defaultGroupedSpecs(spec),
    highlights: defaultHighlights(spec),
    reviews: [],
  };
}

/**
 * Mapea un `ProductMockVariantSpec` al `ProductVariant` del catálogo
 * (shape mínima para cards y componentes que no necesitan el shape
 * rico del PDP). Phase 7: popula `finishSlug`/`finishLabel`/`condition`.
 */
function specVariantToCatalogVariant(
  _spec: ProductMockSpec,
  variant: ProductMockVariantSpec,
): CatalogVariant {
  return {
    id: variant.slug,
    sku: variant.sku,
    ...(variant.finishSlug !== undefined && { finishSlug: variant.finishSlug }),
    ...(variant.finishLabel !== undefined && { finishLabel: variant.finishLabel }),
    ...(variant.hex !== undefined && { finishHex: variant.hex }),
    ...(variant.condition !== undefined && { condition: variant.condition }),
    stock: variant.stock ?? DEFAULT_VARIANT_STOCK,
  };
}

/**
 * Crea un `RelatedProduct` (card "productos relacionados"). Si no
 * se pasa variant explícita, usa la default.
 */
export function createRelatedProduct(
  spec: ProductMockSpec,
  options: { readonly id?: number; readonly variant?: ProductMockVariantSpec } = {},
): RelatedProduct {
  const variant = options.variant ?? defaultVariant(spec.variants);
  const id = options.id ?? stableIdFromSlug(spec.slug);

  const secondary = buildImageUrl(spec, variant.slug, 'back', variant.backSku);

  return {
    id,
    slug: spec.slug,
    name: spec.name,
    priceCents: spec.priceCents,
    ...(spec.compareAtPriceCents !== undefined && {
      compareAtPriceCents: spec.compareAtPriceCents,
    }),
    currency: spec.currency ?? 'USD',
    imageUrl: buildImageUrl(spec, variant.slug, 'front', variant.backSku),
    imageUrlSecondary: secondary,
    ...(spec.badges !== undefined && { badges: [...spec.badges] }),
    vendor: spec.brand,
    finish: variant.displayName,
    variantsCount: spec.variants.length,
    variants: spec.variants.map((v) => ({
      id: v.slug,
      label: v.displayName,
      hex: v.hex ?? '#888888',
      imageUrl: buildImageUrl(spec, v.slug, 'front', v.backSku),
    })),
  };
}

/**
 * Hash determinístico del slug → número estable para el campo `id`
 * de mocks (mismo slug siempre devuelve el mismo id entre sesiones).
 */
function stableIdFromSlug(slug: string): number {
  let hash = 0;
  for (let i = 0; i < slug.length; i += 1) {
    hash = (hash * 31 + slug.charCodeAt(i)) | 0;
  }
  // Reservar rango 9000+ para guitarras (distinto de 1-12 de PRODUCTS).
  return 9000 + (Math.abs(hash) % 999);
}

/** Specs planos por defecto. */
function defaultSpecs(spec: ProductMockSpec): { label: string; value: string }[] {
  const defaultV = defaultVariant(spec.variants);
  return [
    { label: 'SKU', value: defaultV.sku },
    { label: 'Categoría', value: spec.category },
    { label: 'Marca', value: spec.brand },
    { label: 'Variantes', value: `${spec.variants.length} acabados` },
  ];
}

/** Specs agrupados por defecto (estilo Gibson layout). */
function defaultGroupedSpecs(
  spec: ProductMockSpec,
): { category: string; items: { label: string; value: string }[] }[] {
  return [
    {
      category: 'General',
      items: [
        { label: 'Marca', value: spec.brand },
        { label: 'Categoría', value: spec.category },
        { label: 'Variantes', value: `${spec.variants.length}` },
      ],
    },
  ];
}

/** Highlights por defecto (icono + etiqueta). */
function defaultHighlights(_spec: ProductMockSpec): ProductHighlightsItem[] {
  return [
    { icon: 'escudo', label: 'Garantía oficial' },
    { icon: 'estrella', label: 'Calidad premium' },
    { icon: 'premio', label: 'Edición destacada' },
  ];
}