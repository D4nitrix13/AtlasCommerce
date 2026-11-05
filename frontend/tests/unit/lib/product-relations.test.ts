/**
 * Tests de los helpers puros de los rieles de recomendación del PDP.
 *
 * Casos cubiertos:
 *   - `dedupeById`: merge de listas sin duplicar ids (gana la primera
 *     aparición) preservando el orden.
 *   - `mergeAlikeProducts`: cadena de fallback del rail "También Te
 *     Puede Gustar" — curados primero, luego categoría, con tope.
 *   - `toRelatedProductCard`: proyección `Product` → `RelatedProduct`
 *     para el card del rail (swatches, vendor, imagen hover).
 */
import { describe, expect, it } from 'vitest';
import {
  dedupeById,
  mergeAlikeProducts,
  toRelatedProductCard,
} from '../../../src/lib/product-relations';
import type { Product, ProductImage, ProductVariant } from '../../../src/types/catalog';

function buildImage(overrides: Partial<ProductImage> = {}): ProductImage {
  return {
    url: '/img/front.webp',
    altText: 'front',
    isPrimary: true,
    isHover: false,
    variantId: null,
    sortOrder: 1,
    srcset: '',
    ...overrides,
  };
}

function buildVariant(overrides: Partial<ProductVariant> = {}): ProductVariant {
  return {
    id: '1',
    name: 'Ebony',
    finishSlug: 'ebony',
    finishLabel: 'Ebony Black',
    finishHex: '#111111',
    condition: 'nuevo',
    stock: 3,
    ...overrides,
  };
}

function buildProduct(overrides: Partial<Product> = {}): Product {
  return {
    id: 10,
    slug: 'bajo-fusion',
    name: 'Bajo Fusion',
    description: 'Bajo eléctrico.',
    priceCents: 179900,
    imageUrl: '/img/front.webp',
    category: 'bajo-electrico',
    stock: 4,
    images: [],
    variants: [],
    ...overrides,
  };
}

/** Producto multi-acabado con foto propia por variante (preview en hover). */
function buildMultiVariantProduct(): Product {
  return buildProduct({
    variants: [
      buildVariant({ id: '1', finishHex: null }),
      buildVariant({ id: '2', name: 'Halo', finishLabel: 'Halo Metallic', finishHex: '#abcdef' }),
    ],
    images: [
      buildImage({ url: '/img/front.webp', variantId: '1' }),
      buildImage({
        url: '/img/halo.webp',
        altText: 'halo',
        isPrimary: false,
        variantId: '2',
        sortOrder: 2,
      }),
    ],
  });
}

describe('dedupeById', () => {
  it('mergea listas sin repetir ids y preserva el orden', () => {
    const first = [{ id: 1 }, { id: 2 }];
    const second = [{ id: 2 }, { id: 3 }, { id: 1 }];

    expect(dedupeById(first, second).map((p) => p.id)).toEqual([1, 2, 3]);
  });

  it('deduplica dentro de una misma lista', () => {
    expect(dedupeById([{ id: 7 }, { id: 7 }, { id: 8 }]).map((p) => p.id)).toEqual([7, 8]);
  });

  it('devuelve vacío cuando no hay listas', () => {
    expect(dedupeById()).toEqual([]);
  });
});

describe('mergeAlikeProducts', () => {
  it('cae al fallback completo cuando no hay curados', () => {
    const fallback = [{ id: 1 }, { id: 2 }];

    expect(mergeAlikeProducts([], fallback, 12).map((p) => p.id)).toEqual([1, 2]);
  });

  it('prioriza los curados y rellena con el fallback sin duplicar', () => {
    const curated = [{ id: 3 }, { id: 1 }];
    const fallback = [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }];

    expect(mergeAlikeProducts(curated, fallback, 12).map((p) => p.id)).toEqual([3, 1, 2, 4]);
  });

  it('respeta el tope de items', () => {
    const curated = [{ id: 1 }];
    const fallback = [{ id: 2 }, { id: 3 }, { id: 4 }];

    expect(mergeAlikeProducts(curated, fallback, 2).map((p) => p.id)).toEqual([1, 2]);
  });
});

describe('toRelatedProductCard', () => {
  it('mapea los campos base del producto', () => {
    const card = toRelatedProductCard(buildProduct());

    expect(card).toMatchObject({
      id: 10,
      slug: 'bajo-fusion',
      name: 'Bajo Fusion',
      priceCents: 179900,
      currency: 'USD',
      imageUrl: '/img/front.webp',
    });
  });

  it('usa el currency del producto y propaga el precio de lista', () => {
    const card = toRelatedProductCard(
      buildProduct({ currency: 'NIO', compareAtPriceCents: 199900 }),
    );

    expect(card.currency).toBe('NIO');
    expect(card.compareAtPriceCents).toBe(199900);
  });

  it('expone vendor desde brand y finish desde la variante principal', () => {
    const card = toRelatedProductCard(
      buildProduct({ brand: 'Fender', variants: [buildVariant()] }),
    );

    expect(card.vendor).toBe('Fender');
    expect(card.finish).toBe('Ebony Black');
  });
});

describe('toRelatedProductCard (variantes e imágenes)', () => {
  it('arma swatches y variantsCount cuando hay 2+ variantes', () => {
    const card = toRelatedProductCard(buildMultiVariantProduct());

    expect(card.variantsCount).toBe(2);
    expect(card.variants).toHaveLength(2);
    expect(card.variants?.[0]?.hex).not.toBe('');
    expect(card.variants?.[1]?.hex).toBe('#abcdef');
    expect(card.variants?.[1]?.imageUrl).toBe('/img/halo.webp');
  });

  it('no muestra variantsCount con una sola variante', () => {
    const card = toRelatedProductCard(buildProduct({ variants: [buildVariant()] }));

    expect(card.variantsCount).toBeUndefined();
    expect(card.variants).toBeUndefined();
  });

  it('deriva la imagen hover como imageUrlSecondary', () => {
    const card = toRelatedProductCard(
      buildProduct({
        images: [
          buildImage(),
          buildImage({
            url: '/img/back.webp',
            altText: 'back',
            isPrimary: false,
            isHover: true,
            sortOrder: 2,
          }),
        ],
      }),
    );

    expect(card.imageUrlSecondary).toBe('/img/back.webp');
  });
});
