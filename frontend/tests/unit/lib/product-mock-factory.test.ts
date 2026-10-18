/**
 * Tests para `product-mock-factory` — helpers brand-agnostic que
 * generan mocks del storefront desde specs declarativos.
 *
 * Cobertura:
 *   - `buildImageUrl`: ambas naming conventions (indexed + keyword),
 *     back_sku opcional, base path custom.
 *   - `createProductMock`: shape de `Product`, usa variant default.
 *   - `createProductDetailMock`: galería completa, variants con attrs.
 *   - `createVariantMock`: precio + sku + imageUrl.
 *   - Idempotencia del id estable (mismo slug → mismo id).
 */
import { describe, expect, it } from 'vitest';
import {
  buildImageUrl,
  createProductDetailMock,
  createProductMock,
  createRelatedProduct,
  createVariantMock,
  type ProductMockSpec,
} from '../../../src/lib/product-mock-factory';
import { FENDER_AMPRO_TELECASTER, FENDER_AMPRO_STRATOCASTER, GIBSON_MOD_SG_HOLY_SHIFT } from '../../../src/lib/product-mock-specs';

describe('buildImageUrl', () => {
  const teleSpec: ProductMockSpec = FENDER_AMPRO_TELECASTER;

  it('indexed naming (Fender): front usa brandSlug+slug+variant+WxH+idx', () => {
    const url = buildImageUrl(teleSpec, '3-color-sunburst', 'front');
    expect(url).toBe(
      '/assets/products/american-professional-telecaster/3-color-sunburst/fender-american-professional-telecaster-3-color-sunburst-1600x525-01.webp',
    );
  });

  it('indexed naming (Fender): back con back_sku usa el SKU en el filename', () => {
    const url = buildImageUrl(teleSpec, '3-color-sunburst', 'back', '0111140700');
    expect(url).toBe(
      '/assets/products/american-professional-telecaster/3-color-sunburst/0111140700-0111140700-1600x525-02.webp',
    );
  });

  it('indexed naming (Fender): back sin back_sku usa brandSlug+slug+variant', () => {
    const url = buildImageUrl(teleSpec, '3-color-sunburst', 'back');
    expect(url).toBe(
      '/assets/products/american-professional-telecaster/3-color-sunburst/fender-american-professional-telecaster-3-color-sunburst-1600x525-02.webp',
    );
  });

  it('keyword naming (Gibson Mod): variant duplicado en filename', () => {
    const spec = GIBSON_MOD_SG_HOLY_SHIFT;
    const url = buildImageUrl(spec, 'holy-shift', 'front');
    expect(url).toBe(
      '/assets/products/gibson-mod-collection-sg-standard-holy-shift/holy-shift/gibson-mod-collection-sg-standard-holy-shift-holy-shift-front-1600x1600.webp',
    );
  });

  it('keyword naming (Gibson Mod): back usa keyword "back"', () => {
    const spec = GIBSON_MOD_SG_HOLY_SHIFT;
    const url = buildImageUrl(spec, 'holy-shift', 'back');
    expect(url).toBe(
      '/assets/products/gibson-mod-collection-sg-standard-holy-shift/holy-shift/gibson-mod-collection-sg-standard-holy-shift-holy-shift-back-1600x1600.webp',
    );
  });

  it('respeta imageBasePath custom', () => {
    const url = buildImageUrl(
      { ...teleSpec, imageBasePath: '/custom/path' },
      '3-color-sunburst',
      'front',
    );
    expect(url.startsWith('/custom/path/')).toBe(true);
  });
});

describe('createProductMock', () => {
  it('genera Product con imagen de la variant default', () => {
    const product = createProductMock(FENDER_AMPRO_TELECASTER);
    expect(product.slug).toBe('american-professional-telecaster');
    expect(product.name).toBe('Fender American Professional Telecaster');
    expect(product.priceCents).toBe(169900);
    expect(product.currency).toBe('USD');
    expect(product.imageUrl).toContain('3-color-sunburst');
    expect(product.category).toBe('guitarra-electrica');
    expect(product.badges).toEqual(['new']);
  });

  it('usa id estable determinístico para mismo slug', () => {
    const a = createProductMock(FENDER_AMPRO_TELECASTER);
    const b = createProductMock(FENDER_AMPRO_TELECASTER);
    expect(a.id).toBe(b.id);
  });

  it('acepta id custom vía options', () => {
    const product = createProductMock(FENDER_AMPRO_TELECASTER, { id: 42 });
    expect(product.id).toBe(42);
  });

  it('reserva rango 9000+ para guitarras', () => {
    const product = createProductMock(FENDER_AMPRO_TELECASTER);
    expect(product.id).toBeGreaterThanOrEqual(9000);
  });
});

describe('createVariantMock', () => {
  it('genera ProductVariant con atributos, sku, precio e imageUrl', () => {
    const variant = createVariantMock(FENDER_AMPRO_TELECASTER, FENDER_AMPRO_TELECASTER.variants[0]!);
    expect(variant.id).toBe('3-color-sunburst');
    expect(variant.sku).toBe('FEND-AMPRO-TEL-0700');
    expect(variant.priceCents).toBe(169900);
    expect(variant.imageUrl).toContain('3-color-sunburst');
  });

  it('aplica stock default (5) cuando variant no lo declara', () => {
    const variant = createVariantMock(FENDER_AMPRO_TELECASTER, FENDER_AMPRO_TELECASTER.variants[0]!);
    expect(variant.stock).toBe(5);
  });
});

describe('createProductDetailMock', () => {
  it('construye galería con 2 imágenes por variant', () => {
    const detail = createProductDetailMock(FENDER_AMPRO_TELECASTER);
    // 7 variants × 2 (front + back) = 14
    expect(detail.images).toHaveLength(14);
  });

  it('expone todas las variants del spec', () => {
    const detail = createProductDetailMock(FENDER_AMPRO_TELECASTER);
    expect(detail.variants).toHaveLength(7);
  });

  it('genera variantAttributes con un grupo (color)', () => {
    const detail = createProductDetailMock(GIBSON_MOD_SG_HOLY_SHIFT);
    expect(detail.variantAttributes).toHaveLength(1);
    expect(detail.variantAttributes[0]?.[0]?.type).toBe('color');
    expect(detail.variantAttributes[0]?.[0]?.id).toBe('holy-shift');
  });

  it('genera specs por defecto (SKU + Marca + Variantes)', () => {
    const detail = createProductDetailMock(FENDER_AMPRO_TELECASTER);
    const labels = detail.specs.map((s) => s.label);
    expect(labels).toContain('SKU');
    expect(labels).toContain('Marca');
    expect(labels).toContain('Variantes');
  });
});

describe('createRelatedProduct', () => {
  it('genera RelatedProduct con imageUrl + imageUrlSecondary', () => {
    const related = createRelatedProduct(FENDER_AMPRO_TELECASTER);
    expect(related.slug).toBe('american-professional-telecaster');
    expect(related.imageUrl).toContain('3-color-sunburst');
    expect(related.imageUrl).toContain('-01.webp');
    expect(related.imageUrlSecondary).toContain('-02.webp');
  });

  it('genera variants con hex e imageUrl', () => {
    const related = createRelatedProduct(FENDER_AMPRO_TELECASTER);
    expect(related.variantsCount).toBe(7);
    expect(related.variants).toHaveLength(7);
    expect(related.variants?.[0]?.hex).toBe('#3B2410');
    expect(related.variants?.[0]?.imageUrl).toContain('3-color-sunburst');
  });

  it('vendor = brand y finish = variant default', () => {
    const related = createRelatedProduct(FENDER_AMPRO_TELECASTER);
    expect(related.vendor).toBe('Fender');
    expect(related.finish).toBe('3-Color Sunburst');
  });
});

describe('id estable (hash determinístico)', () => {
  it('mismo slug → mismo id en invocaciones distintas', () => {
    expect(createProductMock(FENDER_AMPRO_TELECASTER).id).toBe(
      createProductMock(FENDER_AMPRO_TELECASTER).id,
    );
    expect(createProductDetailMock(FENDER_AMPRO_TELECASTER).id).toBe(
      createProductDetailMock(FENDER_AMPRO_TELECASTER).id,
    );
  });

  it('ids distintos para slugs distintos', () => {
    const tele = createProductMock(FENDER_AMPRO_TELECASTER).id;
    const strat = createProductMock(FENDER_AMPRO_STRATOCASTER).id;
    expect(tele).not.toBe(strat);
  });
});