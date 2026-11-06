/**
 * Tests unitarios para `product-card-swap` — la lógica que
 * `ProductCard.astro` consume para renderizar swatches y cross-fade.
 *
 * Cobertura:
 *   - Resolución de primary: `isPrimary: true` tiene prioridad sobre
 *     `sortOrder`.
 *   - Resolución de hover: por el flag `isHover`, y degradación a
 *     "no hay swap" cuando ninguna foto lo tiene. La cascada de roles
 *     (`back` → `backbody` → `body`) ya NO vive acá: se transladó al
 *     flag en la API (slice 1.5) y su equivalencia se prueba del lado
 *     backend contra catálogo sembrado.
 *   - Swatch row: ≥2 variants → renderiza; 1 variant → no renderiza.
 *   - `VariantSwatch.hex` null → fallback slate-400 (sin error).
 *   - `buildStackedImages`: stack incluye primary + back + variantes
 *     (cuando hay row), evita duplicados por URL.
 *   - `collectVariantIdsInStack`: qué variants quedan con `<img>` real
 *     después de la deduplicación por `src`.
 *   - `buildVariantHoverCss`: una regla de swap por variant con imagen
 *     en el stack, una regla de escape (primera siempre visible) por
 *     variant SIN imagen propia, y espejado a `:focus-visible`.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
  buildStackedImages,
  buildVariantHoverCss,
  collectVariantIdsInStack,
  deriveProductCardSwap,
  shouldRenderSwatchRow,
  swatchFallbackHex,
  type ProductCardSwapData,
  type StackedImage,
  type VariantSwatch,
} from '../../../src/lib/product-card-swap';
import type { Product, ProductImage, ProductVariant } from '../../../src/types/catalog';

function imageWith(
  overrides: Partial<ProductImage> & Pick<ProductImage, 'url' | 'altText'>,
): ProductImage {
  return {
    isPrimary: false,
    // Por defecto NINGUNA foto es de hover, así que cada test tiene que
    // marcarlo explícitamente. Con el default en `true` el "no hay hover"
    // sería imposible de expresar.
    isHover: false,
    variantId: '1',
    sortOrder: 0,
    srcset: '',
    ...overrides,
  };
}

function variantWith(
  overrides: Partial<ProductVariant> & Pick<ProductVariant, 'id'>,
): ProductVariant {
  return {
    sku: 'SKU',
    finishSlug: overrides.id,
    finishLabel: overrides.id,
    finishHex: '#000000',
    condition: 'nuevo',
    stock: 1,
    ...overrides,
  };
}

function swatchWith(id: string): VariantSwatch {
  return { id, label: id, hex: '#000000', thumbnailUrl: null, thumbnailSrcset: null };
}

/** Ids de swatch que la fila renderiza para un producto dado. */
function swatchIdsOf(swap: ProductCardSwapData): string[] {
  return swap.swatches.map((swatch) => swatch.id);
}

/**
 * Reproduce el caso real de `usa-texan`: dos finishes que comparten la
 * MISMA fotografía (versión zurda y diestra del mismo acabado). Las dos
 * variants tienen entrada en `swap.variantImages`, pero
 * `buildStackedImages` deduplica por `src` y deja una sola `<img>` por
 * archivo — o sea, el DOM tiene `data-variant-id="87"` y nada más.
 */
function productWithSharedFinishPhoto(): Product {
  return {
    id: 1,
    slug: 'usa-texan',
    name: 'USA Texan',
    description: 'desc',
    priceCents: 100,
    imageUrl: 'placeholder',
    category: 'guitars',
    stock: 1,
    variants: [
      variantWith({ id: '87', finishLabel: 'Left Handed Vintage Sunburst' }),
      variantWith({ id: '88', finishLabel: 'Vintage Sunburst' }),
    ],
    images: [
      imageWith({ url: 'f7e87a91a99f.webp', altText: 'USA Texan - vista front', isPrimary: true, variantId: '87', sortOrder: 0 }),
      imageWith({ url: 'f7e87a91a99f.webp', altText: 'USA Texan - vista front', isPrimary: false, variantId: '88', sortOrder: 1 }),
      imageWith({ url: '8ead2ae5d194.webp', altText: 'USA Texan - vista back', isPrimary: false, variantId: '87', sortOrder: 2 }),
      imageWith({ url: '8ead2ae5d194.webp', altText: 'USA Texan - vista back', isPrimary: false, variantId: '88', sortOrder: 3 }),
    ],
  };
}

/** CSS que los componentes emiten para un producto + su stack renderizado. */
function cssFor(product: Product): { css: string; swap: ProductCardSwapData; stack: StackedImage[] } {
  const swap = deriveProductCardSwap(product);
  const stack = buildStackedImages(product, swap, shouldRenderSwatchRow(swap.swatches));
  return { css: buildVariantHoverCss(swap, stack), swap, stack };
}

describe('deriveProductCardSwap — primary & hover resolution', () => {
  it('elige la imagen con isPrimary=true sobre cualquier otro criterio', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [variantWith({ id: 'v1' })],
      images: [
        imageWith({ url: 'front-1.webp', altText: 'SG - vista front', isPrimary: true, isHover: false, variantId: 'v1', sortOrder: 5 }),
        imageWith({ url: 'hover-1.webp', altText: 'SG - vista back', isPrimary: false, isHover: true, variantId: 'v1', sortOrder: 0 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.primaryImage?.url).toBe('front-1.webp');
    expect(swap.hoverImage?.url).toBe('hover-1.webp');
    expect(swap.primaryVariantId).toBe('v1');
  });

  it('falls back to sortOrder cuando no hay isPrimary=true', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'a.webp', altText: 'front', sortOrder: 2 }),
        imageWith({ url: 'b.webp', altText: 'front', sortOrder: 0 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.primaryImage?.url).toBe('b.webp');
  });

  it('toma la foto marcada con isHover, sea cual sea su posición', () => {
    // La posición en la galería no dice nada: la ranura la marca el flag.
    // Antes la cascada de roles elegía por `sort_order` implícito y la
    // foto de hover podía caer antes o después de la portada.
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'beauty.webp', altText: 'SG - vista beauty', isPrimary: false, isHover: false, variantId: 'v1', sortOrder: 0 }),
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, isHover: false, variantId: 'v1', sortOrder: 1 }),
        imageWith({ url: 'body.webp', altText: 'SG - vista body', isPrimary: false, isHover: true, variantId: 'v1', sortOrder: 2 }),
        imageWith({ url: 'pickup.webp', altText: 'SG - vista pickup', isPrimary: false, isHover: false, variantId: 'v1', sortOrder: 3 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.hoverImage?.url).toBe('body.webp');
  });

  it('ignora por completo el altText cuando el flag está en otro lado', () => {
    // El caso que la cascada de roles resolvía distinto: dos candidatas,
    // y el texto dice una cosa mientras el flag dice otra. Manda el flag.
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, isHover: false, variantId: 'v1', sortOrder: 0 }),
        imageWith({ url: 'back.webp', altText: 'SG - vista back', isPrimary: false, isHover: false, variantId: 'v1', sortOrder: 1 }),
        imageWith({ url: 'body.webp', altText: 'SG - vista body', isPrimary: false, isHover: true, variantId: 'v1', sortOrder: 2 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.hoverImage?.url).toBe('body.webp');
  });

  it('degrada a NO swap cuando ninguna foto tiene isHover', () => {
    // Producto con una sola foto: es lo que pasa con un producto nuevo
    // subido por la API sin marcar hover. Lo que NO puede pasar es una
    // card en blanco (el bug que ya arreglamos una vez): la portada
    // queda a la vista y no entra ningún `<img data-role="back">` al DOM.
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, isHover: false, variantId: 'v1', sortOrder: 0 }),
        imageWith({ url: 'pickup.webp', altText: 'SG - vista pickup', isPrimary: false, isHover: false, variantId: 'v1', sortOrder: 1 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.hoverImage).toBeNull();

    const stack = buildStackedImages(product, swap, false);
    expect(stack).toHaveLength(1);
    expect(stack[0]?.role).toBe('primary');
    expect(stack[0]?.src).toBe('front.webp');
    expect(stack.some((image) => image.role === 'back')).toBe(false);
  });

  it('toma la foto de hover aunque tenga el mismo texto de alt que otra', () => {
    // El backend replica la marca en todas las resoluciones, y el
    // storefront las contrae a una entrada. Cuando dos entradas
    // comparten `altText`, sólo el flag las distingue.
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, isHover: false, variantId: 'v1', sortOrder: 0 }),
        imageWith({ url: 'back-400.webp', altText: 'SG - vista back', isPrimary: false, isHover: true, variantId: 'v1', sortOrder: 1, srcset: '/a.webp 400w, /b.webp 1600w' }),
        imageWith({ url: 'back-1600.webp', altText: 'SG - vista back', isPrimary: false, isHover: false, variantId: 'v1', sortOrder: 1 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.hoverImage?.url).toBe('back-400.webp');
  });
});

describe('deriveProductCardSwap — swatch row', () => {
  it('devuelve un swatch por acabado distinto, en orden de variantes', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG Standard',
      description: 'SG',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [
        variantWith({ id: 'v1', finishLabel: 'Vintage Burst', finishHex: '#7C3A1A' }),
        variantWith({ id: 'v2', finishLabel: 'Iced Tea', finishHex: '#C89B6A' }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.swatches).toHaveLength(2);
    expect(swap.swatches[0]?.label).toBe('Vintage Burst');
    expect(swap.swatches[0]?.hex).toBe('#7C3A1A');
  });

  it('prefiere `name` (Phase 9) sobre `finishLabel` (Phase 7) para label', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [
        variantWith({ id: 'v1', name: 'Front', finishLabel: 'fallback-label' }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.swatches[0]?.label).toBe('Front');
  });

  it('cae al `id` si el variant no tiene `name` ni `finishLabel`', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [variantWith({ id: 'v-unique', finishLabel: undefined, finishHex: null })],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.swatches[0]?.label).toBe('v-unique');
  });

  it('mantiene en la fila el finish que no aporta foto propia', () => {
    // Decisión de la card: el swatch se renderiza igual. Ocultarlo
    // escondería un acabado realmente comprable (el zurdo de `usa-texan`)
    // y desincronizaría el contador "N colores" de la fila.
    const { swap } = cssFor(productWithSharedFinishPhoto());

    expect(swatchIdsOf(swap)).toEqual(['87', '88']);
  });
});

describe('deriveProductCardSwap — un swatch por acabado', () => {
  it('colapsa las tallas del mismo acabado a un solo swatch (sin círculos repetidos)', () => {
    // Caso real `taza-ceramica` / `playera-manga-corta`: 2 acabados × 2
    // tallas, foto compartida entre tallas. Sin colapsar, la fila pintaba
    // 4 círculos con 2 fotos repetidas y el contador decía "4 colores".
    const { swap } = cssFor(productWithSizeSiblingsSharedPhoto());

    expect(swatchIdsOf(swap)).toEqual(['1', '4']);
    expect(swap.swatches.map((s) => s.label)).toEqual(['Blanco', 'Negro']);
    expect(swap.swatches.map((s) => s.thumbnailUrl)).toEqual(['blanco.webp', 'negro.webp']);
  });

  it('oculta la fila cuando todas las tallas comparten un único acabado', () => {
    // Caso real `hoodie`: 4 tallas del mismo color. "N colores" no aplica
    // y la fila sería un control muerto de círculos idénticos.
    const swap = deriveProductCardSwap(productWithSingleFinishSizes());

    expect(swap.swatches).toHaveLength(1);
    expect(shouldRenderSwatchRow(swap.swatches)).toBe(false);
  });

  it('normaliza primaryVariantId al swatch representante del acabado', () => {
    // La portada pertenece a la talla '3', pero el círculo del acabado
    // es el del representante del grupo ('1'): sin normalizar, ningún
    // swatch quedaba marcado como activo.
    const swap = deriveProductCardSwap(productWithSizeSiblingsSharedPhoto());

    expect(swap.primaryVariantId).toBe('1');
  });

  it('cada swatch del acabado matchea una <img> del stack (el swap no se rompe)', () => {
    // Un `data-swatch-id` sin `<img data-variant-id>` propio cae al
    // escape (portada fija): las tallas hermanas no pueden comerse la
    // foto del acabado al deduplicar el stack por `src`.
    const { swap, stack } = cssFor(productWithSizeSiblingsSharedPhoto());

    expect([...collectVariantIdsInStack(stack)].sort()).toEqual(swatchIdsOf(swap).sort());
  });
});

describe('shouldRenderSwatchRow', () => {
  it('false con 0 o 1 swatch (control muerto)', () => {
    expect(shouldRenderSwatchRow([])).toBe(false);
    expect(shouldRenderSwatchRow([
      { id: 'v1', label: 'L', hex: '#000', thumbnailUrl: null, thumbnailSrcset: null },
    ])).toBe(false);
  });

  it('true con 2+ swatches', () => {
    const swatches: VariantSwatch[] = [swatchWith('v1'), swatchWith('v2')];
    expect(shouldRenderSwatchRow(swatches)).toBe(true);
  });
});

describe('swatchFallbackHex', () => {
  it('devuelve slate-400 cuando hex es null', () => {
    const swatch: VariantSwatch = {
      id: 'v1',
      label: 'L',
      hex: null,
      thumbnailUrl: null,
      thumbnailSrcset: null,
    };
    expect(swatchFallbackHex(swatch)).toBe('#94a3b8');
  });

  it('devuelve slate-400 cuando hex es string vacío', () => {
    const swatch: VariantSwatch = {
      id: 'v1',
      label: 'L',
      hex: '',
      thumbnailUrl: null,
      thumbnailSrcset: null,
    };
    expect(swatchFallbackHex(swatch)).toBe('#94a3b8');
  });

  it('respeta el hex del swatch cuando es válido', () => {
    const swatch: VariantSwatch = {
      id: 'v1',
      label: 'L',
      hex: '#abcdef',
      thumbnailUrl: null,
      thumbnailSrcset: null,
    };
    expect(swatchFallbackHex(swatch)).toBe('#abcdef');
  });
});

describe('buildStackedImages', () => {
  it('siempre arranca con la imagen primaryImage cuando existe', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'primary.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
      ],
    };
    const swap = deriveProductCardSwap(product);
    const stack = buildStackedImages(product, swap, false);
    expect(stack[0]?.role).toBe('primary');
    expect(stack[0]?.src).toBe('primary.webp');
  });

  it('cae al product.imageUrl legacy cuando la gallery es vacía', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'legacy.webp',
      category: 'guitars',
      stock: 1,
      // Sin `images` — caso mock Kitsune.
    };
    const swap = deriveProductCardSwap(product);
    const stack = buildStackedImages(product, swap, false);
    expect(stack).toHaveLength(1);
    expect(stack[0]?.role).toBe('primary');
    expect(stack[0]?.src).toBe('legacy.webp');
  });

  it('incluye el back image como segunda entrada (sin duplicar la primaria)', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      images: [
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, isHover: false, variantId: 'v1' }),
        imageWith({ url: 'back.webp', altText: 'SG - vista back', isPrimary: false, isHover: true, variantId: 'v1' }),
      ],
    };
    const swap = deriveProductCardSwap(product);
    const stack = buildStackedImages(product, swap, false);
    expect(stack.map((i) => i.src)).toEqual(['front.webp', 'back.webp']);
  });

  it('incluye variant images sólo cuando hay swatch row', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [
        variantWith({ id: 'v1' }),
        variantWith({ id: 'v2' }),
      ],
      images: [
        imageWith({ url: 'front-v1.webp', altText: 'SG v1 - vista front', isPrimary: true, variantId: 'v1' }),
        imageWith({ url: 'front-v2.webp', altText: 'SG v2 - vista front', isPrimary: false, variantId: 'v2' }),
      ],
    };
    const swap = deriveProductCardSwap(product);
    const withoutRow = buildStackedImages(product, swap, false);
    const withRow = buildStackedImages(product, swap, true);
    expect(withoutRow.some((i) => i.role === 'variant')).toBe(false);
    expect(withRow.some((i) => i.role === 'variant')).toBe(true);
  });
});

/** Producto con dos finishes y foto propia para cada uno (`wilshire-p-90s`). */
function productWithDistinctFinishPhotos(): Product {
  return {
    id: 1,
    slug: 'wilshire',
    name: 'Wilshire P-90s',
    description: 'desc',
    priceCents: 100,
    imageUrl: 'placeholder',
    category: 'guitars',
    stock: 1,
    variants: [variantWith({ id: 'v1' }), variantWith({ id: 'v2' })],
    images: [
      imageWith({ url: 'front-v1.webp', altText: 'Wilshire - vista front', isPrimary: true, variantId: 'v1' }),
      imageWith({ url: 'back-v1.webp', altText: 'Wilshire - vista back', isPrimary: false, variantId: 'v1' }),
      imageWith({ url: 'front-v2.webp', altText: 'Wilshire v2 - vista front', isPrimary: false, variantId: 'v2' }),
    ],
  };
}

/** Producto con variantes pero sin galería rica (stack legacy de 1 imagen). */
function productWithoutRichImages(): Product {
  return {
    id: 1,
    slug: 'sg',
    name: 'SG',
    description: 'desc',
    priceCents: 100,
    imageUrl: 'legacy.webp',
    category: 'guitars',
    stock: 1,
    variants: [variantWith({ id: 'only' }), variantWith({ id: 'other' })],
  };
}

/** Producto cuyo `variantId` incluye el literal que el espejo busca. */
function productWithHostileVariantId(): Product {
  const hostileId = 'x"):hover)[data-y="z';
  return {
    id: 1,
    slug: 'sg',
    name: 'SG',
    description: 'desc',
    priceCents: 100,
    imageUrl: 'placeholder',
    category: 'guitars',
    stock: 1,
    variants: [variantWith({ id: 'v1' }), variantWith({ id: hostileId })],
    images: [
      imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
      imageWith({ url: 'front-v2.webp', altText: 'SG v2 - vista front', isPrimary: false, variantId: hostileId }),
    ],
  };
}

/**
 * 2 acabados × 2 tallas = 4 variantes, foto compartida entre tallas del
 * mismo acabado (caso real `taza-ceramica`: ids 1/3 blanco, 4/5 negro).
 * La portada la aporta la talla '3' para ejercitar la normalización del
 * representante.
 */
function productWithSizeSiblingsSharedPhoto(): Product {
  return {
    id: 1,
    slug: 'taza-ceramica',
    name: 'Taza cerámica',
    description: 'desc',
    priceCents: 100,
    imageUrl: 'placeholder',
    category: 'drinkware',
    stock: 1,
    variants: [
      variantWith({ id: '1', finishSlug: 'blanco', finishLabel: 'Blanco', finishHex: '#F7F4EF', sizeSlug: '11-oz' }),
      variantWith({ id: '3', finishSlug: 'blanco', finishLabel: 'Blanco', finishHex: '#F7F4EF', sizeSlug: '15-oz' }),
      variantWith({ id: '4', finishSlug: 'negro', finishLabel: 'Negro', finishHex: '#1C1917', sizeSlug: '11-oz' }),
      variantWith({ id: '5', finishSlug: 'negro', finishLabel: 'Negro', finishHex: '#1C1917', sizeSlug: '15-oz' }),
    ],
    images: [
      imageWith({ url: 'blanco.webp', altText: 'Taza - vista front', isPrimary: false, variantId: '1', sortOrder: 1 }),
      imageWith({ url: 'blanco.webp', altText: 'Taza - vista front', isPrimary: true, variantId: '3', sortOrder: 1 }),
      imageWith({ url: 'negro.webp', altText: 'Taza - vista front', isPrimary: false, variantId: '4', sortOrder: 1 }),
      imageWith({ url: 'negro.webp', altText: 'Taza - vista front', isPrimary: false, variantId: '5', sortOrder: 1 }),
    ],
  };
}

/** 4 tallas de un único acabado (caso real `hoodie`): la fila de colores no aplica. */
function productWithSingleFinishSizes(): Product {
  const negro = { finishSlug: 'negro', finishLabel: 'Negro', finishHex: '#1C1917' };
  return {
    id: 1,
    slug: 'hoodie',
    name: 'Hoodie',
    description: 'desc',
    priceCents: 100,
    imageUrl: 'placeholder',
    category: 'apparel',
    stock: 1,
    variants: [
      variantWith({ id: '14', ...negro, sizeSlug: 'ch' }),
      variantWith({ id: '15', ...negro, sizeSlug: 'm' }),
      variantWith({ id: '16', ...negro, sizeSlug: 'g' }),
      variantWith({ id: '17', ...negro, sizeSlug: 'xg' }),
    ],
    images: [
      imageWith({ url: 'hoodie.webp', altText: 'Hoodie - vista front', isPrimary: true, variantId: '14', sortOrder: 1 }),
    ],
  };
}

describe('collectVariantIdsInStack', () => {
  it('devuelve el set de variantId que tienen <img> en el stack', () => {
    const { stack } = cssFor(productWithDistinctFinishPhotos());

    expect([...collectVariantIdsInStack(stack)].sort()).toEqual(['v1', 'v2']);
  });

  it('excluye el variantId null de la imagen legacy sin galería rica', () => {
    const product: Product = { ...productWithoutRichImages(), variants: undefined };
    const { stack } = cssFor(product);

    expect(stack).toHaveLength(1);
    expect(stack[0]?.variantId).toBeNull();
    expect(collectVariantIdsInStack(stack).size).toBe(0);
  });

  it('NO incluye el variant cuya foto fue deduplicada por otra variant', () => {
    // Este es el corazón del fix: `swap.variantImages` dice que 88 tiene
    // foto, el DOM no. Filtrar por el mapa dejaba una regla de swap
    // huérfana y la card en blanco.
    const { swap, stack } = cssFor(productWithSharedFinishPhoto());

    expect(swap.variantImages.has('88')).toBe(true);
    expect(stack.filter((image) => image.variantId === '88')).toHaveLength(0);
    expect([...collectVariantIdsInStack(stack)]).toEqual(['87']);
  });
});

describe('buildVariantHoverCss — regla de swap', () => {
  it('emite la regla de swap para cada variant con foto propia', () => {
    const { css } = cssFor(productWithDistinctFinishPhotos());

    expect(css).toContain(
      '.productcard-swap-root:has([data-swatch-id="v1"]:hover) .productcard-swap-img[data-variant-id="v1"]{opacity:1;}',
    );
    expect(css).toContain(
      '.productcard-swap-root:has([data-swatch-id="v2"]:hover) .productcard-swap-img[data-variant-id="v2"]{opacity:1;}',
    );
  });

  it('no emite escape para un variant con foto propia (sigue swapeando)', () => {
    const { css } = cssFor(productWithDistinctFinishPhotos());

    expect(css).not.toContain('img.productcard-swap-img[data-role="primary"]');
  });

  it('el swatch activo conserva su regla de swap y no recibe escape', () => {
    // El activo ya está en el stack (es la primaria) y su caso lo cubre la
    // regla `data-swatch-active` del <style> scoped del componente: emitir
    // escape acá sería una regla redundante.
    const { css } = cssFor(productWithSharedFinishPhoto());

    expect(css).toContain(
      '.productcard-swap-root:has([data-swatch-id="87"]:hover) .productcard-swap-img[data-variant-id="87"]{opacity:1;}',
    );
    expect(css).not.toContain('[data-swatch-id="87"]:hover) img.productcard-swap-img');
  });

  it('devuelve string vacío cuando no hay swatches', () => {
    const product: Product = { ...productWithoutRichImages(), variants: undefined };

    expect(cssFor(product).css).toBe('');
  });
});

describe('buildVariantHoverCss — escape para el swatch sin foto propia', () => {
  it('emite la regla que deja la primaria visible para ese swatch', () => {
    // Swatch 88 no aporta <img> propia: sin escape, el hover lo dejaba en
    // blanco (bug medido en Chromium sobre `usa-texan`).
    const { css } = cssFor(productWithSharedFinishPhoto());

    expect(css).toContain(
      '.productcard-swap-root:has(.productcard-swap-swatch[data-swatch-id="88"]:hover) img.productcard-swap-img[data-role="primary"][data-variant-id]{opacity:1;}',
    );
  });

  it('elimina la regla de swap huérfana que dejaba la card en blanco', () => {
    const { css } = cssFor(productWithSharedFinishPhoto());

    // El selector `[data-variant-id="88"]` no matchea nada en el DOM:
    // dead CSS que además escondía la única imagen visible.
    expect(css).not.toContain('[data-variant-id="88"]');
  });

  it('cubre también al producto legacy, donde ningún swatch tiene foto', () => {
    const { css } = cssFor(productWithoutRichImages());

    expect(css).toContain('img.productcard-swap-img[data-role="primary"][data-variant-id]{opacity:1;}');
    expect(css).not.toContain('[data-variant-id="only"]');
  });
});

describe('buildVariantHoverCss — cssEscape y espejo de teclado', () => {
  it('espeja a :focus-visible todas las reglas, de swap y de escape', () => {
    // Es exactamente la transformación que hacen los dos .astro: el
    // replace tiene que cubrir las dos familias de reglas.
    const { css } = cssFor(productWithSharedFinishPhoto());
    const mirrored = css.replaceAll(':hover)', ':focus-visible)');

    expect(mirrored).not.toContain(':hover)');
    expect(mirrored).toContain('[data-swatch-id="88"]:focus-visible)');
    expect(mirrored).toContain('[data-swatch-id="87"]:focus-visible)');
  });

  it('el espejado no toca un variant id que contiene el literal :hover)', () => {
    // Sin escapar `)` en `cssEscape`, el replaceAll del componente
    // reescribiría el valor del atributo y dejaría la regla inservible.
    const { css } = cssFor(productWithHostileVariantId());
    const mirrored = css.replaceAll(':hover)', ':focus-visible)');
    // El `)` del id queda `\)`, así que dentro del valor de atributo ya no
    // existe el literal `:hover)` que el replace busca.
    const escapedId = 'x\\"\\):hover\\)[data-y=\\"z';

    expect(css).toContain(escapedId);
    expect(mirrored).toContain(escapedId);
    expect(mirrored).not.toContain(':hover)');
    expect(mirrored.match(/:focus-visible\)/g)).toHaveLength(2);
  });

  it('escapa caracteres especiales en el variant id (defense in depth)', () => {
    const product: Product = {
      ...productWithoutRichImages(),
      imageUrl: 'placeholder',
      variants: [variantWith({ id: 'tricky"id' })],
      images: [
        imageWith({ url: 't.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'tricky"id' }),
      ],
    };
    // El `"` se escapa a `\"` para que no rompa el selector de atributo.
    expect(cssFor(product).css).toContain('tricky\\"id');
  });
});

describe('ProductCard / ProductCardCatalog — contrato del CSS de swatches', () => {
  const components = [
    resolve(__dirname, '../../../src/components/storefront/products/ProductCard.astro'),
    resolve(
      __dirname,
      '../../../src/components/storefront/products/ProductCardCatalog.astro',
    ),
  ];

  it.each(components)('%s arma el CSS con el stack renderizado', (path) => {
    const source = readFileSync(path, 'utf-8');
    // `swap.variantImages` es la fuente equivocada: no sabe qué fotos
    // quedaron en el DOM después de deduplicar por `src`.
    expect(source).toContain('buildVariantHoverCss(swap, stackedImages)');
  });

  it.each(components)('%s conserva las reglas de escape del swatch activo', (path) => {
    const source = readFileSync(path, 'utf-8');
    // El fix NO puede reemplazar este mecanismo: sigue siendo el que
    // cubre al swatch activo (su `data-variant-id` es el de la primaria).
    expect(source).toContain(
      '.productcard-swap-root:has(.productcard-swap-swatch[data-swatch-active]:hover)',
    );
    expect(source).toContain(
      '.productcard-swap-root:has(.productcard-swap-swatch[data-swatch-active]:focus-visible)',
    );
  });

  it.each(components)('%s renderiza data-variant-id en cada img del stack', (path) => {
    const source = readFileSync(path, 'utf-8');
    // El selector de escape apunta a `[data-variant-id]`: sin el atributo
    // la regla no matchea y la card vuelve a quedar en blanco.
    expect(source).toContain("data-variant-id={image.variantId ?? ''}");
  });
});
