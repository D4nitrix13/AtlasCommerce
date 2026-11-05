// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';
// Vite raw import — evita dependencia de @types/node.
import typesSource from '../../../src/i18n/types.ts?raw';

/**
 * Cobertura de las keys `storefront.catalog.states.*` (estados del PLP,
 * WU2 de catalogo-plp-busqueda): error del API (≠ vacío) y variante
 * "categoría sin resultados".
 *
 * Mismo contrato que `catalog-chips.test.ts`: si alguien borra o
 * renombra una key, falla acá antes de que el estado renderice
 * `undefined`. Keys aditivas — `storefront.products.v3.*` y
 * `storefront.catalog.chips.*` quedan como están.
 */
const REQUIRED_KEYS = [
  'storefront.catalog.states.error',
  'storefront.catalog.states.errorRetry',
  'storefront.catalog.states.emptyCategory',
  'storefront.catalog.states.emptyCategoryClear',
] as const;

const esDict = es as Record<string, string>;
const enDict = en as Record<string, string>;

describe('i18n · storefront.catalog.states (WU2 estados del PLP)', () => {
  it('es.ts contiene TODAS las keys de estados declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(esDict[key], `es.ts missing key: ${key}`).toBeTypeOf('string');
      expect(esDict[key].length, `es.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('en.ts contiene TODAS las keys de estados declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(enDict[key], `en.ts missing key: ${key}`).toBeTypeOf('string');
      expect(enDict[key].length, `en.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('types.ts declara TODAS las keys en el union TranslationKey', () => {
    for (const key of REQUIRED_KEYS) {
      expect(typesSource, `types.ts missing key: ${key}`).toContain(`'${key}'`);
    }
  });
});
