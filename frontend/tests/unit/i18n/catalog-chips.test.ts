// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';

/**
 * Cobertura de las keys `storefront.catalog.chips.*` (chips de filtros
 * activos del PLP, WU1 de catalogo-plp-busqueda).
 *
 * Mismo contrato que `products-v3.test.ts`: si alguien borra o
 * renombra una key, falla acá antes de que el chip renderice
 * `undefined`. Keys aditivas — `storefront.products.v3.*` queda como está.
 *
 * Placeholders (convención `{varName}` de `i18n/types.ts`):
 *   - `removeAriaLabel` → `{label}`
 *   - `priceBoth` → `{min}` y `{max}`; `priceMin` → `{min}`; `priceMax` → `{max}`
 */
const REQUIRED_KEYS = [
  'storefront.catalog.chips.removeAriaLabel',
  'storefront.catalog.chips.priceBoth',
  'storefront.catalog.chips.priceMin',
  'storefront.catalog.chips.priceMax',
] as const;

const esDict = es as Record<string, string>;
const enDict = en as Record<string, string>;

describe('i18n · storefront.catalog.chips (WU1 chips de filtros activos)', () => {
  it('es.ts contiene TODAS las keys de chips declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(esDict[key], `es.ts missing key: ${key}`).toBeTypeOf('string');
      expect(esDict[key].length, `es.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('en.ts contiene TODAS las keys de chips declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(enDict[key], `en.ts missing key: ${key}`).toBeTypeOf('string');
      expect(enDict[key].length, `en.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('expone los placeholders {label}/{min}/{max}', () => {
    for (const dict of [esDict, enDict]) {
      expect(dict['storefront.catalog.chips.removeAriaLabel']).toContain('{label}');
      expect(dict['storefront.catalog.chips.priceBoth']).toContain('{min}');
      expect(dict['storefront.catalog.chips.priceBoth']).toContain('{max}');
      expect(dict['storefront.catalog.chips.priceMin']).toContain('{min}');
      expect(dict['storefront.catalog.chips.priceMax']).toContain('{max}');
    }
  });
});
