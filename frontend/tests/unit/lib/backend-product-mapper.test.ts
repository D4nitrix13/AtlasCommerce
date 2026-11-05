import { describe, expect, it } from 'vitest';
import {
  mapBackendToProduct,
  type BackendProduct,
} from '../../../src/lib/backend-product-mapper';
import type { ProductImage } from '../../../src/types/catalog';

/** `images` es unión (legacy string[] | ProductImage[]): estrechar acá. */
function richImages(product: { images?: readonly string[] | readonly ProductImage[] }): ProductImage[] {
  return (product.images ?? []).filter((image): image is ProductImage => typeof image === 'object');
}

function buildBackendProduct(overrides: Partial<BackendProduct> = {}): BackendProduct {
  return {
    id: 7,
    slug: 'taza-ceramica',
    name: 'Taza cerámica',
    description: 'Taza de cerámica para estampado.',
    priceCents: 1200,
    imageUrl: '/api/v1/storefront/assets/tenants/x/products/taza/abc.webp',
    stock: 10,
    category: 'drinkware',
    brand: 'Panda',
    isExclusive: false,
    images: [
      {
        url: '/api/v1/storefront/assets/tenants/x/products/taza/front.webp',
        altText: 'Taza cerámica - vista front',
        isPrimary: true,
        isHover: false,
        variantId: 3,
        sortOrder: 1,
        srcset: '/api/… 400w',
      },
      {
        url: '/api/v1/storefront/assets/tenants/x/products/taza/back.webp',
        altText: 'Taza cerámica - vista back',
        isPrimary: false,
        isHover: true,
        variantId: 3,
        sortOrder: 2,
        srcset: '/api/… 400w',
      },
    ],
    variants: [
      {
        id: 3,
        name: 'Blanco',
        finishSlug: 'blanco',
        finishLabel: 'Blanco',
        finishHex: null,
        condition: 'nuevo',
        stock: 10,
        handedness: null,
      },
    ],
    ...overrides,
  };
}

describe('mapBackendToProduct', () => {
  it('maps fields and normalizes variant ids to string', () => {
    const product = mapBackendToProduct(buildBackendProduct());

    expect(product.slug).toBe('taza-ceramica');
    expect(product.priceCents).toBe(1200);
    // El backend emite variantId/id como int; el swap los compara por
    // igualdad estricta como string — si uno queda number, el swatch
    // pierde el lookup en silencio.
    expect(product.variants?.[0]?.id).toBe('3');
    expect(richImages(product)[0]?.variantId).toBe('3');
  });

  it('flags the hover image from the API contract', () => {
    const product = mapBackendToProduct(buildBackendProduct());

    const hover = richImages(product).find((image) => image.isHover);
    expect(hover).toBeDefined();
  });

  it('tolerates missing images and variants', () => {
    const product = mapBackendToProduct(buildBackendProduct({ images: undefined, variants: undefined }));

    expect(product.images).toEqual([]);
    expect(product.variants).toEqual([]);
  });

  it('falls back to the placeholder when imageUrl is null', () => {
    const product = mapBackendToProduct(buildBackendProduct({ imageUrl: null }));

    expect(product.imageUrl).toBe('/images/product-placeholder.png');
  });
});
