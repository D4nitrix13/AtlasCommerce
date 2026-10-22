/**
 * Tests unitarios para `product-card-swap` — la lógica que
 * `ProductCard.astro` consume para renderizar swatches y cross-fade.
 *
 * Cobertura:
 *   - `extractRole` (indirecto vía `deriveProductCardSwap`).
 *   - Resolución de primary: `isPrimary: true` tiene prioridad sobre
 *     `sortOrder`.
 *   - Resolución de back: cascada `back` → `backbody` → `body`, y
 *     fallback a la galería completa cuando el variant activo no
 *     tiene la imagen buscada.
 *   - Swatch row: ≥2 variants → renderiza; 1 variant → no renderiza.
 *   - `VariantSwatch.hex` null → fallback slate-400 (sin error).
 *   - `buildStackedImages`: stack incluye primary + back + variantes
 *     (cuando hay row), evita duplicados por URL.
 *   - `buildVariantHoverCss`: una regla `:has(:hover)` por variant
 *     con imagen, sin reglas huérfanas para variants sin imagen.
 */
import { describe, expect, it } from 'vitest';
import {
  buildStackedImages,
  buildVariantHoverCss,
  deriveProductCardSwap,
  shouldRenderSwatchRow,
  swatchFallbackHex,
  type VariantSwatch,
} from '../../../src/lib/product-card-swap';
import type { Product, ProductImage, ProductVariant } from '../../../src/types/catalog';

function imageWith(
  overrides: Partial<ProductImage> & Pick<ProductImage, 'url' | 'altText'>,
): ProductImage {
  return {
    isPrimary: false,
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

describe('deriveProductCardSwap — primary & back resolution', () => {
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
        imageWith({ url: 'front-1.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1', sortOrder: 5 }),
        imageWith({ url: 'back-1.webp', altText: 'SG - vista back', isPrimary: false, variantId: 'v1', sortOrder: 0 }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.primaryImage?.url).toBe('front-1.webp');
    expect(swap.backImage?.url).toBe('back-1.webp');
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

  it('resuelve back por cascada back → backbody → body', () => {
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
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
        imageWith({ url: 'pickup.webp', altText: 'SG - vista pickup', isPrimary: false, variantId: 'v1' }),
        imageWith({ url: 'body.webp', altText: 'SG - vista body', isPrimary: false, variantId: 'v1' }),
        imageWith({ url: 'backbody.webp', altText: 'SG - vista backbody', isPrimary: false, variantId: 'v1' }),
        imageWith({ url: 'back.webp', altText: 'SG - vista back', isPrimary: false, variantId: 'v1' }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    // El primer match de la cascada gana: `back` antes de `backbody`
    // antes de `body`. Los roles detail (`pickup`, etc.) se ignoran.
    expect(swap.backImage?.url).toBe('back.webp');
  });

  it('cae al rol siguiente de la cascada cuando back falta', () => {
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
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
        imageWith({ url: 'backbody.webp', altText: 'SG - vista backbody', isPrimary: false, variantId: 'v1' }),
        imageWith({ url: 'pickup.webp', altText: 'SG - vista pickup', isPrimary: false, variantId: 'v1' }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.backImage?.url).toBe('backbody.webp');
  });

  it('devuelve null para back si ningún rol es válido', () => {
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
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
        imageWith({ url: 'pickup.webp', altText: 'SG - vista pickup', isPrimary: false, variantId: 'v1' }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.backImage).toBeNull();
  });

  it('NO toma el back de otro acabado si el primary variant tiene sus propias fotos', () => {
    // Caso: la variante activa (v1) sólo tiene front; otra variante (v2)
    // tiene back. No se usa el back de v2.
    //
    // En una tienda de instruments el acabado ES la identidad del SKU:
    // si la carta anuncia Ébony y al hover aparece la espalda de
    // Sunburst, se está mostrando otro producto del que el que se vende.
    // Es el mismo error que usar un primer plano del clavijero como
    // portada, sólo que en la dirección contraria.
    //
    // Cuando la variante principal no trae ninguna foto el gallery es
    // del producto entero y sí se usa como fuente — ese caso lo cubre
    // el test de arriba.
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
        imageWith({ url: 'front-v1.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
        imageWith({ url: 'back-v2.webp', altText: 'SG - vista back', isPrimary: false, variantId: 'v2' }),
      ],
    };

    const swap = deriveProductCardSwap(product);
    expect(swap.backImage).toBeNull();
  });
});

describe('deriveProductCardSwap — swatch row', () => {
  it('devuelve un swatch por variant sin filtrar', () => {
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
});

describe('shouldRenderSwatchRow', () => {
  it('false con 0 o 1 swatch (control muerto)', () => {
    expect(shouldRenderSwatchRow([])).toBe(false);
    expect(shouldRenderSwatchRow([
      { id: 'v1', label: 'L', hex: '#000', thumbnailUrl: null, thumbnailSrcset: null },
    ])).toBe(false);
  });

  it('true con 2+ swatches', () => {
    const swatches: VariantSwatch[] = [
      { id: 'v1', label: 'A', hex: '#000', thumbnailUrl: null, thumbnailSrcset: null },
      { id: 'v2', label: 'B', hex: '#111', thumbnailUrl: null, thumbnailSrcset: null },
    ];
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
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'v1' }),
        imageWith({ url: 'back.webp', altText: 'SG - vista back', isPrimary: false, variantId: 'v1' }),
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

describe('buildVariantHoverCss', () => {
  it('emite una regla por variant con imagen y omite las huérfanas', () => {
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
        variantWith({ id: 'has-image' }),
        variantWith({ id: 'no-image' }),
      ],
      images: [
        imageWith({ url: 'front.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'has-image' }),
        // `no-image` no tiene imagen atada.
      ],
    };
    const swap = deriveProductCardSwap(product);
    const css = buildVariantHoverCss(swap);
    expect(css).toContain('data-swatch-id="has-image"');
    expect(css).toContain('data-variant-id="has-image"');
    expect(css).not.toContain('data-swatch-id="no-image"');
  });

  it('devuelve string vacío cuando no hay variants con imagen', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [variantWith({ id: 'lonely' })],
    };
    const swap = deriveProductCardSwap(product);
    expect(buildVariantHoverCss(swap)).toBe('');
  });

  it('escapa caracteres especiales en el variant id (defense in depth)', () => {
    const product: Product = {
      id: 1,
      slug: 'sg',
      name: 'SG',
      description: 'desc',
      priceCents: 100,
      imageUrl: 'placeholder',
      category: 'guitars',
      stock: 1,
      variants: [variantWith({ id: 'tricky"id' })],
      images: [
        imageWith({ url: 't.webp', altText: 'SG - vista front', isPrimary: true, variantId: 'tricky"id' }),
      ],
    };
    const swap = deriveProductCardSwap(product);
    const css = buildVariantHoverCss(swap);
    // El `"` se escapa a `\"` para que no rompa el selector de atributo.
    expect(css).toContain('tricky\\"id');
  });
});
