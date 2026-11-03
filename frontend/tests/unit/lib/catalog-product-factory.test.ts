/**
 * Tests unitarios de `catalog-product-factory` — factory pura que
 * construye `Product` en la shape rica (imágenes front+back por
 * variante, swatches, badges, compareAt) para tenants sin fotos reales.
 *
 * Cobertura:
 *   - Galería: 2 imágenes (front+back) por acabado, exactamente un
 *     `isPrimary` y un `isHover`, la back nunca es primary, y el
 *     `variantId` de cada imagen coincide con su variante.
 *   - Variantes: finishSlug/finishLabel/finishHex/condition/stock.
 *   - Badges normalizados (`lowStock` → `low_stock`).
 *   - `compareAtPriceCents` solo convive con badge `sale`.
 *   - `isExclusive` default false; stock total = suma de variants (o el
 *     explícito del seed).
 *   - `buildGeneratedImageUrl`: data-URI SVG distinto por rol.
 */
import { describe, expect, it } from 'vitest';
import {
  buildGeneratedImageUrl,
  createCatalogProduct,
  createCatalogProducts,
  type ProductSeed,
} from '../../../src/lib/catalog-product-factory';

const TWO_FINISHES: ProductSeed = {
  id: 9101,
  slug: 'polo-pique',
  name: 'Polo piqué',
  description: 'Polo de piqué para uniforme.',
  priceCents: 2400,
  category: 'textil',
  kind: 'polo',
  brand: 'Panda Estampados',
  finishes: [
    { slug: 'negro', label: 'Negro', hex: '#1C1917', stock: 20 },
    { slug: 'crema', label: 'Crema', hex: '#F3EDE4', stock: 20 },
  ],
};

function isRichImages(
  images: readonly string[] | readonly { url: string }[] | undefined,
): images is readonly {
  url: string;
  altText: string;
  isPrimary: boolean;
  isHover: boolean;
  variantId: string | null;
  sortOrder: number;
  srcset: string;
}[] {
  return Array.isArray(images) && typeof images[0] === 'object';
}

describe('createCatalogProduct', () => {
  it('genera front+back por acabado (2 acabados → 4 imágenes)', () => {
    const product = createCatalogProduct(TWO_FINISHES);
    expect(product.images).toHaveLength(4);
    expect(isRichImages(product.images)).toBe(true);
  });

  it('tiene exactamente un isPrimary y un isHover, y la back nunca es primary', () => {
    const product = createCatalogProduct(TWO_FINISHES);
    const images = product.images as readonly {
      isPrimary: boolean;
      isHover: boolean;
      altText: string;
    }[];
    expect(images.filter((image) => image.isPrimary)).toHaveLength(1);
    expect(images.filter((image) => image.isHover)).toHaveLength(1);
    const hover = images.find((image) => image.isHover);
    expect(hover?.isPrimary).toBe(false);
    expect(hover?.altText).toContain('back');
  });

  it('asocia cada imagen a la variante que le corresponde', () => {
    const product = createCatalogProduct(TWO_FINISHES);
    const images = product.images as readonly { variantId: string | null; sortOrder: number }[];
    const variantIds = (product.variants ?? []).map((variant) => variant.id);
    expect(variantIds).toHaveLength(2);
    // front (sort 0) y back (sort 1) → variante 0; front (2) y back (3) → variante 1
    expect(images[0]?.variantId).toBe(variantIds[0]);
    expect(images[1]?.variantId).toBe(variantIds[0]);
    expect(images[2]?.variantId).toBe(variantIds[1]);
    expect(images[3]?.variantId).toBe(variantIds[1]);
  });

  it('construye variantes con finish, condition y stock', () => {
    const product = createCatalogProduct(TWO_FINISHES);
    const [first, second] = product.variants ?? [];
    expect(first).toMatchObject({
      finishSlug: 'negro',
      finishLabel: 'Negro',
      finishHex: '#1C1917',
      condition: 'nuevo',
      stock: 20,
    });
    expect(second?.finishSlug).toBe('crema');
  });

  it('normaliza badges (alias lowStock → low_stock)', () => {
    const product = createCatalogProduct({
      ...TWO_FINISHES,
      badges: ['new', 'lowStock'],
    });
    expect(product.badges).toEqual(['new', 'low_stock']);
  });

  it('incluye compareAtPriceCents solo cuando hay badge sale', () => {
    const onSale = createCatalogProduct({
      ...TWO_FINISHES,
      badges: ['sale'],
      compareAtPriceCents: 3000,
    });
    expect(onSale.compareAtPriceCents).toBe(3000);

    const notOnSale = createCatalogProduct({
      ...TWO_FINISHES,
      compareAtPriceCents: 3000,
    });
    expect(notOnSale.compareAtPriceCents).toBeUndefined();
  });

  it('isExclusive defaultea a false', () => {
    expect(createCatalogProduct(TWO_FINISHES).isExclusive).toBe(false);
    expect(
      createCatalogProduct({ ...TWO_FINISHES, isExclusive: true }).isExclusive,
    ).toBe(true);
  });

  it('stock total = suma de variants, o el explícito del seed', () => {
    expect(createCatalogProduct(TWO_FINISHES).stock).toBe(40);
    expect(createCatalogProduct({ ...TWO_FINISHES, stock: 7 }).stock).toBe(7);
  });

  it('expone imageUrl igual a la imagen primary', () => {
    const product = createCatalogProduct(TWO_FINISHES);
    const images = product.images as readonly { url: string; isPrimary: boolean }[];
    expect(product.imageUrl).toBe(images.find((image) => image.isPrimary)?.url);
  });
});

describe('createCatalogProducts', () => {
  it('mapea cada seed a un Product', () => {
    const products = createCatalogProducts([
      TWO_FINISHES,
      { ...TWO_FINISHES, id: 9102, slug: 'playera', name: 'Playera' },
    ]);
    expect(products).toHaveLength(2);
    expect(products.map((product) => product.slug)).toEqual(['polo-pique', 'playera']);
  });
});

describe('buildGeneratedImageUrl', () => {
  it('devuelve un data-URI SVG distinto por rol', () => {
    const front = buildGeneratedImageUrl(TWO_FINISHES, 'negro', 'front');
    const back = buildGeneratedImageUrl(TWO_FINISHES, 'negro', 'back');
    expect(front.startsWith('data:image/svg+xml')).toBe(true);
    expect(back.startsWith('data:image/svg+xml')).toBe(true);
    expect(front).not.toBe(back);
    expect(decodeURIComponent(front.slice('data:image/svg+xml,'.length))).not.toBe(
      decodeURIComponent(back.slice('data:image/svg+xml,'.length)),
    );
  });
});
