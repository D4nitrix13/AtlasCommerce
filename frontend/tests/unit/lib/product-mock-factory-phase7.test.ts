/**
 * Tests para los campos Phase 7 en `product-mock-factory`.
 *
 * Cubre la propagación de los nuevos campos introducidos en la Fase 7
 * (schema rich de productos) desde el spec al output del factory:
 *   - `Product.isExclusive` y `Product.sizes` desde el spec
 *   - `Product.variants[]` con `finishSlug`/`finishLabel`/`finishHex`/
 *     `condition`/`stock` por variant
 *   - `ProductDetailData.isExclusive`, `sizes` y los campos
 *     `finishSlug`/`finishLabel`/`finishHex`/`condition`/`sizeSlug`
 *     en el rich `ProductVariant` del PDP
 *
 * Happy path + edge cases:
 *   - Spec sin `isExclusive` ni `sizes` → output NO los incluye
 *     (preserva comportamiento backward-compatible)
 *   - Spec con `isExclusive: true` → output lo expone
 *   - Spec con `sizes: []` (vacío) → output NO incluye `sizes`
 *   - Spec con `sizes: [...]` (no vacío) → output los mapea 1-a-1
 */
import { describe, expect, it } from 'vitest';
import {
  createProductDetailMock,
  createProductMock,
  type ProductMockSpec,
} from '../../../src/lib/product-mock-factory';

const BASE_VARIANT = {
  slug: 'sunburst',
  displayName: 'Sunburst',
  sku: 'TEST-SUN',
} as const;

/** Spec minimal para tests Phase 7 — sin sizes ni isExclusive. */
function baseSpec(overrides: Partial<ProductMockSpec> = {}): ProductMockSpec {
  return {
    slug: 'test-product',
    name: 'Test Product',
    brand: 'Test Brand',
    brandSlug: 'test-brand',
    category: 'Test Category',
    categorySlug: 'test-category',
    description: 'A test product',
    priceCents: 10000,
    variants: [BASE_VARIANT],
    imagePattern: {
      naming: 'indexed',
      frontSize: '1600x1600',
      backSize: '1600x1600',
      frontIndex: '01',
      backIndex: '02',
    },
    ...overrides,
  };
}

describe('createProductMock — isExclusive / sizes', () => {
  it('omits isExclusive cuando el spec no lo declara (backward-compat)', () => {
    const product = createProductMock(baseSpec());
    expect(product.isExclusive).toBeUndefined();
  });

  it('populates isExclusive=true desde el spec', () => {
    const product = createProductMock(baseSpec({ isExclusive: true }));
    expect(product.isExclusive).toBe(true);
  });

  it('NO omite el flag cuando isExclusive=false explícito', () => {
    // Solo isExclusive=true se materializa en el output (omit-when-false
    // pattern — preserva contratos JSON estrictos). false explícito
    // cae igual que undefined.
    const product = createProductMock(baseSpec({ isExclusive: false }));
    expect(product.isExclusive).toBeUndefined();
  });

  it('populates sizes[] cuando el spec las declara no vacías', () => {
    const product = createProductMock(
      baseSpec({
        sizes: [
          { slug: '44', label: '4/4', position: 1 },
          { slug: '34', label: '3/4', position: 2 },
        ],
      }),
    );
    expect(product.sizes).toEqual([
      { slug: '44', label: '4/4', position: 1 },
      { slug: '34', label: '3/4', position: 2 },
    ]);
  });

  it('omits sizes cuando el array está vacío', () => {
    const product = createProductMock(baseSpec({ sizes: [] }));
    expect(product.sizes).toBeUndefined();
  });

  it('omite position cuando el spec no la declara', () => {
    const product = createProductMock(
      baseSpec({ sizes: [{ slug: 'm', label: 'Medium' }] }),
    );
    expect(product.sizes?.[0]).toEqual({ slug: 'm', label: 'Medium' });
    expect(product.sizes?.[0]).not.toHaveProperty('position');
  });
});

describe('createProductMock — variants[] con Phase 7 fields', () => {
  function buildVariantSpec(): ProductMockSpec {
    return baseSpec({
      variants: [
        {
          slug: 'sunburst',
          displayName: 'Sunburst',
          sku: 'TEST-SUN',
          finishSlug: 'sunburst',
          finishLabel: 'Sunburst',
          hex: '#7C3A1A',
          condition: 'nuevo',
          stock: 3,
          isDefault: true,
        },
        {
          slug: 'negro',
          displayName: 'Negro',
          sku: 'TEST-NEG',
          finishSlug: 'negro',
          finishLabel: 'Negro',
          condition: 'usado',
          stock: 1,
        },
      ],
    });
  }

  it('mapea finishSlug/finishLabel/finishHex/condition/stock por variant', () => {
    const product = createProductMock(buildVariantSpec());

    expect(product.variants).toHaveLength(2);
    expect(product.variants?.[0]).toMatchObject({
      id: 'sunburst',
      sku: 'TEST-SUN',
      finishSlug: 'sunburst',
      finishLabel: 'Sunburst',
      finishHex: '#7C3A1A',
      condition: 'nuevo',
      stock: 3,
    });
    expect(product.variants?.[1]).toMatchObject({
      id: 'negro',
      sku: 'TEST-NEG',
      finishSlug: 'negro',
      condition: 'usado',
      stock: 1,
    });
  });

  it('NO incluye finishSlug cuando el variant no lo tiene', () => {
    const product = createProductMock(
      baseSpec({
        variants: [{ ...BASE_VARIANT }],
      }),
    );
    expect(product.variants?.[0]).toEqual({
      id: 'sunburst',
      sku: 'TEST-SUN',
      stock: 5, // DEFAULT_VARIANT_STOCK del factory
    });
    expect(product.variants?.[0]).not.toHaveProperty('finishSlug');
    expect(product.variants?.[0]).not.toHaveProperty('finishLabel');
    expect(product.variants?.[0]).not.toHaveProperty('condition');
  });

  it('combina isExclusive + sizes + variants en un solo output', () => {
    const product = createProductMock(
      baseSpec({
        isExclusive: true,
        sizes: [{ slug: 's', label: 'Small', position: 0 }],
        variants: [
          {
            ...BASE_VARIANT,
            finishSlug: 'red',
            finishLabel: 'Red',
            condition: 'ambos',
          },
        ],
      }),
    );
    expect(product.isExclusive).toBe(true);
    expect(product.sizes).toEqual([{ slug: 's', label: 'Small', position: 0 }]);
    expect(product.variants?.[0]).toMatchObject({
      finishSlug: 'red',
      finishLabel: 'Red',
      condition: 'ambos',
    });
  });
});

describe('createProductDetailMock — isExclusive / sizes', () => {
  it('propaga isExclusive al ProductDetailData', () => {
    const detail = createProductDetailMock(baseSpec({ isExclusive: true }));
    expect(detail.isExclusive).toBe(true);
  });

  it('omits isExclusive cuando el spec no lo declara', () => {
    const detail = createProductDetailMock(baseSpec());
    expect(detail.isExclusive).toBeUndefined();
  });

  it('propaga sizes[] al ProductDetailData', () => {
    const detail = createProductDetailMock(
      baseSpec({
        sizes: [
          { slug: '44', label: '4/4 (Full Size)', position: 1 },
          { slug: '34', label: '3/4', position: 2 },
        ],
      }),
    );
    expect(detail.sizes).toEqual([
      { slug: '44', label: '4/4 (Full Size)', position: 1 },
      { slug: '34', label: '3/4', position: 2 },
    ]);
  });
});

describe('createProductDetailMock — rich ProductVariant', () => {
  it('incluye finishSlug/finishLabel/finishHex/condition/sizeSlug', () => {
    const detail = createProductDetailMock(
      baseSpec({
        variants: [
          {
            slug: 'sunburst',
            displayName: '3-Color Sunburst',
            sku: 'TEST-SUN-001',
            finishSlug: 'sunburst',
            finishLabel: '3-Color Sunburst',
            hex: '#3B2410',
            condition: 'nuevo',
          },
        ],
      }),
    );
    const variant = detail.variants[0];
    expect(variant).toBeDefined();
    expect(variant).toMatchObject({
      id: 'sunburst',
      sku: 'TEST-SUN-001',
      finishSlug: 'sunburst',
      finishLabel: '3-Color Sunburst',
      finishHex: '#3B2410',
      condition: 'nuevo',
    });
  });

  it('omite finish fields cuando el spec no los tiene', () => {
    const detail = createProductDetailMock(
      baseSpec({
        variants: [{ ...BASE_VARIANT }],
      }),
    );
    const variant = detail.variants[0];
    expect(variant).toBeDefined();
    expect(variant).not.toHaveProperty('finishSlug');
    expect(variant).not.toHaveProperty('finishLabel');
    expect(variant).not.toHaveProperty('finishHex');
    expect(variant).not.toHaveProperty('condition');
  });
});