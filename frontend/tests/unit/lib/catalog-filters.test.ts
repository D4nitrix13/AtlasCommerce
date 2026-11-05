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
 *   - `buildActiveFilterChips`: un chip por filtro activo (categoría,
 *     color, precio min+max = UNO), sin chip de sort ni page; cada href
 *     remueve su filtro y resetea `page` a 1.
 *
 * Unidades: los params de URL `minPrice`/`maxPrice` viajan en USD (los
 * labels del sidebar son "$0 – $30"); el dominio trabaja en cents.
 */
import { describe, expect, it } from 'vitest';
import {
  applyCatalogFilters,
  buildActiveFilterChips,
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

const CHIP_LABELS = {
  priceBoth: 'Precio: {min} – {max}',
  priceMin: 'Precio: desde {min}',
  priceMax: 'Precio: hasta {max}',
  categories: { textil: 'Textil' },
  colors: { negro: 'Negro' },
};

describe('buildActiveFilterChips — qué filtros generan chip', () => {
  it('devuelve vacío sin filtros activos — ni chips de sort ni de page', () => {
    expect(buildActiveFilterChips('/es/products', {}, CHIP_LABELS)).toEqual([]);
    expect(
      buildActiveFilterChips('/es/products', { sort: 'price-asc', page: 3 }, CHIP_LABELS),
    ).toEqual([]);
  });

  it('chip de categoría: label del mapa y href que remueve el filtro y resetea page', () => {
    expect(
      buildActiveFilterChips('/es/products', { category: 'textil', page: 3 }, CHIP_LABELS),
    ).toEqual([{ label: 'Textil', href: '/es/products?page=1' }]);
  });

  it('chip de color: label del mapa y href que lo remueve', () => {
    expect(buildActiveFilterChips('/es/products', { color: 'negro' }, CHIP_LABELS)).toEqual([
      { label: 'Negro', href: '/es/products?page=1' },
    ]);
  });

  it('min+max de precio = UN solo chip con la plantilla both', () => {
    expect(
      buildActiveFilterChips(
        '/es/products',
        { minPriceCents: 1000, maxPriceCents: 10000, page: 2 },
        CHIP_LABELS,
      ),
    ).toEqual([{ label: 'Precio: $10 – $100', href: '/es/products?page=1' }]);
  });
});

describe('buildActiveFilterChips — labels y hrefs', () => {
  it('usa el slug como fallback cuando el mapa no trae label', () => {
    expect(
      buildActiveFilterChips('/es/products', { category: 'drinkware' }, CHIP_LABELS)[0].label,
    ).toBe('drinkware');
  });

  it('precio con solo min / solo max usan sus plantillas', () => {
    expect(
      buildActiveFilterChips('/es/products', { minPriceCents: 3000 }, CHIP_LABELS),
    ).toEqual([{ label: 'Precio: desde $30', href: '/es/products?page=1' }]);
    expect(
      buildActiveFilterChips('/es/products', { maxPriceCents: 6000 }, CHIP_LABELS),
    ).toEqual([{ label: 'Precio: hasta $60', href: '/es/products?page=1' }]);
  });

  it('varios filtros activos: un chip cada uno, en orden categoría/color/precio', () => {
    const chips = buildActiveFilterChips(
      '/es/products',
      {
        category: 'textil',
        color: 'negro',
        minPriceCents: 0,
        maxPriceCents: 3000,
        sort: 'price-asc',
        page: 4,
      },
      CHIP_LABELS,
    );

    expect(chips.map((chip) => chip.label)).toEqual([
      'Textil',
      'Negro',
      'Precio: $0 – $30',
    ]);
  });

  it('cada href conserva los demás filtros activos', () => {
    const filters = { category: 'textil', color: 'negro' };
    const chips = buildActiveFilterChips('/es/products', filters, CHIP_LABELS);

    expect(chips[0].href).toBe('/es/products?color=negro&page=1');
    expect(chips[1].href).toBe('/es/products?category=textil&page=1');
  });
});
