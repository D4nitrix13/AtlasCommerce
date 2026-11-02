/**
 * Tests de la lógica pura de filtros del PLP (`src/lib/catalog-filters.ts`).
 *
 * Casos cubiertos:
 *   - `parseCatalogFilters`: query completa, valores inválidos ignorados
 *     (precios negativos/no numéricos, sort desconocido, page no numérico).
 *   - `applyCatalogFilters`: categoría, color vía variantes, rango de
 *     precio (ambos extremos / solo min / solo max) y orden (precio
 *     asc/desc, nombre). La paginación NO vive acá.
 *   - `buildCatalogHref`: mergea el patch sobre los filtros actuales,
 *     limpia params vacíos y resetea `page` a 1 salvo que el patch traiga
 *     su propio `page`.
 *
 * Unidades: los params de URL `minPrice`/`maxPrice` viajan en USD (los
 * labels del sidebar son "$0 – $30"); el dominio trabaja en cents.
 */
import { describe, expect, it } from 'vitest';
import {
  applyCatalogFilters,
  buildCatalogHref,
  parseCatalogFilters,
} from '../../../src/lib/catalog-filters';

const product = (
  name: string,
  priceCents: number,
  category: string,
  finishSlugs: readonly string[] = [],
) => ({
  name,
  priceCents,
  category,
  variants: finishSlugs.map((finishSlug) => ({ finishSlug })),
});

const PLAYERA = product('Playera', 1800, 'textil', ['negro']);
const TAZA = product('Taza', 1200, 'drinkware', ['blanco']);
const HOODIE = product('Hoodie', 4200, 'textil', ['marino', 'negro']);

const CATALOG = [PLAYERA, TAZA, HOODIE];

describe('parseCatalogFilters', () => {
  it('parsea una query completa', () => {
    const params = new URLSearchParams(
      'category=textil&color=negro&minPrice=10&maxPrice=100&sort=price-asc&page=3',
    );

    expect(parseCatalogFilters(params)).toEqual({
      category: 'textil',
      color: 'negro',
      minPriceCents: 1000,
      maxPriceCents: 10000,
      sort: 'price-asc',
      page: 3,
    });
  });

  it('ignora valores inválidos: precios negativos/no numéricos, sort desconocido y page no numérico', () => {
    const params = new URLSearchParams(
      'category=&color=negro&minPrice=-5&maxPrice=abc&sort=desconocido&page=xyz',
    );

    expect(parseCatalogFilters(params)).toEqual({ color: 'negro' });
  });

  it('ignora page cero o negativa y precios vacíos', () => {
    const params = new URLSearchParams('page=0&minPrice=&maxPrice=-1');

    expect(parseCatalogFilters(params)).toEqual({});
  });

  it('acepta decimales en los precios (USD → cents)', () => {
    const params = new URLSearchParams('minPrice=24.5&maxPrice=30');

    expect(parseCatalogFilters(params)).toEqual({
      minPriceCents: 2450,
      maxPriceCents: 3000,
    });
  });
});

describe('applyCatalogFilters', () => {
  it('filtra por categoría', () => {
    expect(applyCatalogFilters(CATALOG, { category: 'textil' })).toEqual([
      PLAYERA,
      HOODIE,
    ]);
  });

  it('filtra por color vía variants[].finishSlug', () => {
    expect(applyCatalogFilters(CATALOG, { color: 'negro' })).toEqual([
      PLAYERA,
      HOODIE,
    ]);
  });

  it('filtra por rango de precio con ambos extremos', () => {
    expect(
      applyCatalogFilters(CATALOG, { minPriceCents: 1300, maxPriceCents: 2000 }),
    ).toEqual([PLAYERA]);
  });

  it('filtra por rango de precio con solo min', () => {
    expect(applyCatalogFilters(CATALOG, { minPriceCents: 2000 })).toEqual([
      HOODIE,
    ]);
  });

  it('filtra por rango de precio con solo max', () => {
    expect(applyCatalogFilters(CATALOG, { maxPriceCents: 1800 })).toEqual([
      PLAYERA,
      TAZA,
    ]);
  });

  it('ordena por precio ascendente', () => {
    expect(applyCatalogFilters(CATALOG, { sort: 'price-asc' })).toEqual([
      TAZA,
      PLAYERA,
      HOODIE,
    ]);
  });

  it('ordena por precio descendente', () => {
    expect(applyCatalogFilters(CATALOG, { sort: 'price-desc' })).toEqual([
      HOODIE,
      PLAYERA,
      TAZA,
    ]);
  });

  it('ordena por nombre', () => {
    expect(applyCatalogFilters(CATALOG, { sort: 'name-asc' })).toEqual([
      HOODIE,
      PLAYERA,
      TAZA,
    ]);
  });

  it('sin sort mantiene el orden original (relevancia)', () => {
    expect(applyCatalogFilters(CATALOG, {})).toEqual(CATALOG);
    expect(applyCatalogFilters(CATALOG, { sort: 'relevancia' })).toEqual(CATALOG);
  });
});

describe('buildCatalogHref', () => {
  it('mergea el patch sobre los filtros actuales y resetea page a 1', () => {
    expect(
      buildCatalogHref('/es/products', { category: 'textil', page: 4 }, { color: 'negro' }),
    ).toBe('/es/products?category=textil&color=negro&page=1');
  });

  it('serializa los precios en USD y emite los buckets del sidebar', () => {
    expect(
      buildCatalogHref('/es/products', {}, { minPriceCents: 0, maxPriceCents: 3000 }),
    ).toBe('/es/products?minPrice=0&maxPrice=30&page=1');
  });

  it('limpia params vacíos y el sort por defecto', () => {
    expect(
      buildCatalogHref(
        '/es/products',
        { category: '', color: 'negro', minPriceCents: undefined, sort: 'relevancia' },
        {},
      ),
    ).toBe('/es/products?color=negro&page=1');
  });

  it('respeta el page del patch cuando viene', () => {
    expect(
      buildCatalogHref('/es/products', { category: 'textil', page: 4 }, { page: 2 }),
    ).toBe('/es/products?category=textil&page=2');
  });

  it('omite el page cuando el patch lo limpia explícitamente (base de paginación)', () => {
    expect(
      buildCatalogHref('/es/products', { category: 'textil', page: 4 }, { page: undefined }),
    ).toBe('/es/products?category=textil');
  });
});
