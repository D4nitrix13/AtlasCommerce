/**
 * Tests para los componentes ProductFAQ y ProductExtendedDescription
 * de Fase 8 + deriveProductDetail Phase 8.
 *
 * Los componentes Astro no se renderizan en jsdom (requieren el
 * compilador Astro) → tests estructurales sobre el source.
 *
 * Cobertura:
 *   - ProductFAQ: empty-safe (no renderiza si vacío), shape del
 *     wrapper (FaqAccordion), labels de i18n propagados.
 *   - ProductExtendedDescription: empty-safe, render del primer
 *     bloque (heading + body), render de múltiples bloques como
 *     <details>.
 *   - deriveProductDetail: expone los 4 arrays extended (highlights,
 *     groupedSpecs, faqs, extendedDescriptions) como arrays vacíos
 *     por default (backward-compat con mocks sin contenido Fase 8).
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { deriveProductDetail } from '../../../../../src/lib/product-detail';
import type { Product } from '../../../../../src/types/catalog';

const PRODUCT_FAQ_ASTRO_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductFAQ.astro',
);
const PRODUCT_FAQ_TYPES_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductFAQ.types.ts',
);
const PRODUCT_EXT_DESC_ASTRO_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductExtendedDescription.astro',
);
const PRODUCT_EXT_DESC_TYPES_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductExtendedDescription.types.ts',
);

const PRODUCT_FAQ_SOURCE = readFileSync(PRODUCT_FAQ_ASTRO_PATH, 'utf-8');
const PRODUCT_FAQ_TYPES_SOURCE = readFileSync(PRODUCT_FAQ_TYPES_PATH, 'utf-8');
const PRODUCT_EXT_DESC_SOURCE = readFileSync(PRODUCT_EXT_DESC_ASTRO_PATH, 'utf-8');
const PRODUCT_EXT_DESC_TYPES_SOURCE = readFileSync(PRODUCT_EXT_DESC_TYPES_PATH, 'utf-8');

describe('ProductFAQ (Fase 8) — shape estructural', () => {
  it('es empty-safe: no renderiza si faqs está vacío', () => {
    // El componente envuelve todo en `{faqs.length > 0 && (...)}` —
    // verificamos que la condición existe y referencia `faqs.length`.
    expect(PRODUCT_FAQ_SOURCE).toMatch(/faqs\.length\s*>\s*0/);
    expect(PRODUCT_FAQ_SOURCE).toMatch(/\{faqs\.length\s*>\s*0\s*&&\s*\(/);
  });

  it('reusa el FaqAccordion genérico de storefront', () => {
    expect(PRODUCT_FAQ_SOURCE).toMatch(/import FaqAccordion from ['"]\.\.\/FaqAccordion\.astro['"]/);
  });

  it('propaga el heading y los items al FaqAccordion', () => {
    expect(PRODUCT_FAQ_SOURCE).toMatch(/<FaqAccordion\s+heading=\{labels\.heading\}\s+items=\{faqs\}\s*\/>/);
  });

  it('declara el shape `question` + `answer` para items', () => {
    expect(PRODUCT_FAQ_TYPES_SOURCE).toMatch(/interface ProductFAQItem/);
    expect(PRODUCT_FAQ_TYPES_SOURCE).toMatch(/question:\s*string/);
    expect(PRODUCT_FAQ_TYPES_SOURCE).toMatch(/answer:\s*string/);
  });

  it('declara labels con heading para i18n', () => {
    expect(PRODUCT_FAQ_TYPES_SOURCE).toMatch(/interface ProductFAQLabels/);
    expect(PRODUCT_FAQ_TYPES_SOURCE).toMatch(/heading:\s*string/);
  });
});

describe('ProductExtendedDescription (Fase 8) — shape estructural', () => {
  it('es empty-safe: no renderiza si items está vacío', () => {
    expect(PRODUCT_EXT_DESC_SOURCE).toMatch(/hasItems\s*=\s*items\.length\s*>\s*0/);
    expect(PRODUCT_EXT_DESC_SOURCE).toMatch(/\{hasItems\s*&&\s*primary\s*!==\s*undefined\s*&&\s*\(/);
  });

  it('renderiza el primer bloque con heading bold + body en prosa', () => {
    expect(PRODUCT_EXT_DESC_SOURCE).toMatch(/<h3[^>]*>\{primary\.title\}<\/h3>/);
  });

  it('renderiza bloques adicionales como <details> colapsables', () => {
    expect(PRODUCT_EXT_DESC_SOURCE).toMatch(/\{rest\.map\(\(block,\s*index\)/);
    expect(PRODUCT_EXT_DESC_SOURCE).toMatch(/<details/);
    expect(PRODUCT_EXT_DESC_SOURCE).toMatch(/<summary/);
  });

  it('declara el shape `title` + `body` para items', () => {
    expect(PRODUCT_EXT_DESC_TYPES_SOURCE).toMatch(/interface ProductExtendedDescriptionItem/);
    expect(PRODUCT_EXT_DESC_TYPES_SOURCE).toMatch(/title:\s*string/);
    expect(PRODUCT_EXT_DESC_TYPES_SOURCE).toMatch(/body:\s*string/);
  });

  it('declara labels con heading para i18n', () => {
    expect(PRODUCT_EXT_DESC_TYPES_SOURCE).toMatch(/interface ProductExtendedDescriptionLabels/);
    expect(PRODUCT_EXT_DESC_TYPES_SOURCE).toMatch(/heading:\s*string/);
  });
});

describe('deriveProductDetail — Fase 8 extended content', () => {
  const baseProduct: Product = {
    id: 1,
    slug: 'fase-8-product',
    name: 'Fase 8 Test',
    description: 'Test product for Fase 8',
    priceCents: 10000,
    imageUrl: '/test.jpg',
    category: 'guitarra-electrica',
    stock: 5,
    badges: [],
    currency: 'USD',
    variants: [],
    sizes: [],
  };

  it('expone los 4 arrays extended como arrays vacíos por default', () => {
    // Los productos derivados sin contenido extended deben tener
    // arrays vacíos (no undefined) para que la PDP los itere sin
    // checks `?? []` y los componentes apliquen empty-safe.
    const detail = deriveProductDetail(baseProduct);

    expect(detail.highlights).toEqual([]);
    expect(detail.groupedSpecs).toEqual([]);
    expect(detail.faqs).toEqual([]);
    expect(detail.extendedDescriptions).toEqual([]);
  });

  it('preserva los demás campos del Product base', () => {
    const detail = deriveProductDetail(baseProduct);

    expect(detail.id).toBe(1);
    expect(detail.slug).toBe('fase-8-product');
    expect(detail.name).toBe('Fase 8 Test');
    expect(detail.priceCents).toBe(10000);
    expect(detail.currency).toBe('USD');
    expect(detail.category).toBe('guitarra-electrica');
    expect(detail.stock).toBe(5);
  });
});