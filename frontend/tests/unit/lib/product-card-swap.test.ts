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

  it('mantiene en la fila el finish que no aporta foto propia', () => {
    // Decisión de la card: el swatch se renderiza igual. Ocultarlo
    // escondería un acabado realmente comprable (el zurdo de `usa-texan`)
    // y desincronizaría el contador "N colores" de la fila.
    const { swap } = cssFor(productWithSharedFinishPhoto());

    expect(swatchIdsOf(swap)).toEqual(['87', '88']);
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
