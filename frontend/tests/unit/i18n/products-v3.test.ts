// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';

/**
 * Cobertura mínima de las keys nuevas del catálogo v3 (vista 04).
 *
 * Estas keys las agregamos en `i18n/types.ts` y se exponen en
 * `i18n/locales/{es,en}.ts`. Este test garantiza que si alguien borra
 * o renombra una de ellas, falla acá antes de que la página renderice
 * literal `undefined`.
 *
 * Convención de keys: `storefront.products.v3.*` (dot notation, sin
 * chocar con `productGrid.*` legacy que mantiene compatibilidad con el
 * `/products` link-based original).
 */
const REQUIRED_KEYS = [
  'storefront.products.v3.title',
  'storefront.products.v3.subtitle',
  'storefront.products.v3.sort.label',
  'storefront.products.v3.sort.relevancia',
  'storefront.products.v3.sort.precioAsc',
  'storefront.products.v3.sort.precioDesc',
  'storefront.products.v3.sort.nombre',
  'storefront.products.v3.filter.heading',
  'storefront.products.v3.filter.clear',
  'storefront.products.v3.filter.categoria',
  'storefront.products.v3.filter.precio',
  'storefront.products.v3.filter.color',
  'storefront.products.v3.filter.talla',
  'storefront.products.v3.filter.colorSwatchAlt',
  'storefront.products.v3.filter.categoriaAll',
  'storefront.products.v3.range.0-30',
  'storefront.products.v3.range.30-60',
  'storefront.products.v3.range.60-100',
  'storefront.products.v3.range.100+',
  'storefront.products.v3.pagination.previous',
  'storefront.products.v3.pagination.next',
  'storefront.products.v3.pagination.page',
  'storefront.products.v3.pagination.of',
  'storefront.products.v3.empty',
  'storefront.products.v3.countRange',
] as const;

describe('i18n · storefront.products.v3 (vista 04 catálogo)', () => {
  it('es.ts contiene TODAS las keys v3 declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(es[key], `es.ts missing key: ${key}`).toBeTypeOf('string');
      expect((es[key] as string).length, `es.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('en.ts contiene TODAS las keys v3 declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(en[key], `en.ts missing key: ${key}`).toBeTypeOf('string');
      expect((en[key] as string).length, `en.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('subtitle y countRange exponen los placeholders {count}/{from}/{to}/{total}', () => {
    expect(es['storefront.products.v3.subtitle']).toContain('{count}');
    expect(en['storefront.products.v3.subtitle']).toContain('{count}');
    expect(es['storefront.products.v3.countRange']).toContain('{from}');
    expect(es['storefront.products.v3.countRange']).toContain('{to}');
    expect(es['storefront.products.v3.countRange']).toContain('{total}');
  });
});