/**
 * Tests estructurales de los estados del PLP en `products/index.astro`
 * (WU2 de catalogo-plp-busqueda): estado error ≠ estado empty y
 * variante "categoría sin resultados".
 *
 * Los `.astro` se testean por source-matching estructural, mismo
 * patrón que `FilterChips.test.ts` y `CatalogStateMessage.test.ts`
 * (este último cubre la forma de la tarjeta de estado). La lógica de
 * filtros/hrefs vive testeada en `tests/unit/lib/catalog-filters.test.ts`.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const INDEX_PATH = resolve(
  __dirname,
  '../../../../../src/pages/[locale]/products/index.astro',
);
const INDEX_SOURCE = readFileSync(INDEX_PATH, 'utf-8');

describe('products/index.astro — loadCatalogPage discriminado (error ≠ empty)', () => {
  it('devuelve resultado discriminado: éxito { ok: true } vs fallo { ok: false }', () => {
    expect(INDEX_SOURCE).toContain('type CatalogPageResult');
    expect(INDEX_SOURCE).toMatch(/type CatalogPageResult[\s\S]*?ok: true[\s\S]*?ok: false/);
    expect(INDEX_SOURCE).toMatch(
      /async function loadCatalogPage[\s\S]{0,120}Promise<CatalogPageResult>/,
    );
    expect(INDEX_SOURCE).toMatch(/return \{\s*ok: false\s*\};/);
    // El bug original: el catch devolvía catálogo vacío y el empty
    // state mentía "0 productos" cuando en realidad el API falló.
    expect(INDEX_SOURCE).not.toMatch(
      /return \{\s*data: \[\],\s*meta: EMPTY_CATALOG_META\s*\};/,
    );
  });

  it('un fallo en el fetch inicial o en el re-fetch del clamp es estado de error', () => {
    const fetchCalls = INDEX_SOURCE.match(/catalogResult\s*=\s*await loadCatalogPage/g) ?? [];
    expect(fetchCalls.length).toBeGreaterThanOrEqual(2);
    expect(INDEX_SOURCE).toMatch(/const hasCatalogError = !catalogResult\.ok/);
    // El flag mira el resultado FINAL: se calcula después del re-fetch.
    expect(INDEX_SOURCE.indexOf('const hasCatalogError')).toBeGreaterThan(
      INDEX_SOURCE.lastIndexOf('catalogResult = await loadCatalogPage'),
    );
  });
});

describe('products/index.astro — render del estado error', () => {
  it('muestra el estado de error con retry link plano (misma URL, sin JS)', () => {
    expect(INDEX_SOURCE).toMatch(/hasCatalogError\s*&&\s*\(?\s*<CatalogStateMessage/);
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.catalog\.states\.error'\]/);
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.catalog\.states\.errorRetry'\]/);
    expect(INDEX_SOURCE).toMatch(
      /const retryHref = `\$\{Astro\.url\.pathname\}\$\{Astro\.url\.search\}`/,
    );
    expect(INDEX_SOURCE).toMatch(/linkHref=\{retryHref\}/);
    expect(INDEX_SOURCE).not.toMatch(/location\.reload|window\.location/i);
  });

  it('en error no se renderizan el conteo "0 productos" ni el sort', () => {
    expect(INDEX_SOURCE).toMatch(/!hasCatalogError\s*&&\s*<p[^>]*>\{subtitle\}/);
    expect(INDEX_SOURCE).toMatch(/!hasCatalogError\s*&&\s*\(?\s*<SortDropdown/);
  });

  it('el fallback Panda no enmascara el error del API', () => {
    expect(INDEX_SOURCE).toMatch(/isPandaStore\(tenantSlug\) && !hasCatalogError/);
  });

  it('importa CatalogStateMessage desde products/', () => {
    expect(INDEX_SOURCE).toMatch(
      /import CatalogStateMessage from '\.\.\/\.\.\/\.\.\/components\/storefront\/products\/CatalogStateMessage\.astro'/,
    );
    expect(INDEX_SOURCE).not.toMatch(/<script/i);
  });
});

describe('products/index.astro — empty state y variante categoría', () => {
  it('con categoría activa y 0 resultados, variante contextual con link que limpia la categoría', () => {
    expect(INDEX_SOURCE).toMatch(/filters\.category\s*\?\s*\(\s*<CatalogStateMessage/);
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.catalog\.states\.emptyCategory'\]/);
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.catalog\.states\.emptyCategoryClear'\]/);
    // Mismo mecanismo de remoción que los chips (buildCatalogHref resetea
    // `page`) — el link funciona sin JS.
    expect(INDEX_SOURCE).toMatch(
      /const clearCategoryHref = buildCatalogHref\(baseUrl, filters, \{ category: undefined \}\)/,
    );
    expect(INDEX_SOURCE).toMatch(/linkHref=\{clearCategoryHref\}/);
  });

  it('documenta que el API no distingue categoría inexistente de categoría con 0 productos', () => {
    expect(INDEX_SOURCE).toMatch(/categoría inexistente/);
  });

  it('el empty state genérico (0 productos) sigue renderizando con las keys existentes', () => {
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.products\.v3\.empty'\]/);
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.productGrid\.emptyHint'\]/);
    expect(INDEX_SOURCE).toMatch(/col-span-full/);
  });

  it('las tres variantes de estado usan CatalogStateMessage', () => {
    const uses = INDEX_SOURCE.match(/<CatalogStateMessage\b/g) ?? [];
    // error + categoría vacía + empty genérico
    expect(uses.length).toBeGreaterThanOrEqual(3);
  });
});
