// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';
// Vite raw import — evita dependencia de @types/node.
import typesSource from '../../../src/i18n/types.ts?raw';

/**
 * Cobertura de las keys `storefront.catalog.filters.*` (trigger del
 * drawer de filtros en móvil, WU3 de catalogo-plp-busqueda).
 *
 * Mismo contrato que `catalog-chips.test.ts` / `catalog-states.test.ts`:
 * keys aditivas en es + en + union `TranslationKey`; si alguien borra o
 * renombra una key, falla acá antes de que el trigger renderice
 * `undefined`.
 */
const REQUIRED_KEYS = [
  'storefront.catalog.filters.trigger',
  'storefront.catalog.filters.triggerCount',
] as const;

const esDict = es as Record<string, string>;
const enDict = en as Record<string, string>;

describe('i18n · storefront.catalog.filters (WU3 trigger móvil)', () => {
  it('es.ts contiene TODAS las keys del trigger declaradas', () => {
    for (const key of REQUIRED_KEYS) {
      expect(esDict[key], `es.ts missing key: ${key}`).toBeTypeOf('string');
      expect(esDict[key].length, `es.ts empty value: ${key}`).toBeGreaterThan(0);
    }
  });

  it('en.ts contiene TODAS las keys del trigger declaradas', () => {
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

  it('la plantilla de conteo lleva el placeholder {count}', () => {
    expect(esDict['storefront.catalog.filters.triggerCount']).toContain('{count}');
    expect(enDict['storefront.catalog.filters.triggerCount']).toContain('{count}');
  });
});
