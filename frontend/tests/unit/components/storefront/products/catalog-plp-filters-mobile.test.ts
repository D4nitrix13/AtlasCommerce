/**
 * Tests estructurales de la integración móvil de filtros en
 * `products/index.astro` (WU3 de catalogo-plp-busqueda): el sidebar
 * deja de ser inalcanzable bajo `lg` gracias a un disclosure nativo.
 *
 * Patrón de `catalog-plp-states.test.ts`: source-matching sobre la
 * página. La forma del disclosure vive en `FiltersDisclosure.test.ts`.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const INDEX_PATH = resolve(
  __dirname,
  '../../../../../src/pages/[locale]/products/index.astro',
);
const INDEX_SOURCE = readFileSync(INDEX_PATH, 'utf-8');

describe('products/index.astro — filtros alcanzables en móvil', () => {
  it('integra FiltersDisclosure (import + render) en vez del sidebar suelto', () => {
    expect(INDEX_SOURCE).toMatch(
      /import FiltersDisclosure from '\.\.\/\.\.\/\.\.\/components\/storefront\/products\/FiltersDisclosure\.astro'/,
    );
    expect(INDEX_SOURCE).toMatch(/<FiltersDisclosure\b/);
    // El sidebar crudo se renderiza DENTRO del wrapper, no en la página.
    expect(INDEX_SOURCE).not.toMatch(/<FiltersSidebar\b/);
    expect(INDEX_SOURCE).not.toMatch(/import FiltersSidebar /);
    // La página sigue sin JS propio.
    expect(INDEX_SOURCE).not.toMatch(/<script/i);
  });

  it('pasa label, plantilla de conteo y la cantidad de filtros activos', () => {
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.catalog\.filters\.trigger'\]/);
    expect(INDEX_SOURCE).toMatch(/t\['storefront\.catalog\.filters\.triggerCount'\]/);
    // El conteo reusa los chips de WU1 (categoría/color/precio activos).
    expect(INDEX_SOURCE).toMatch(/activeCount=\{activeFilterChips\.length\}/);
  });

  it('conserva la grilla 2 columnas y los props del sidebar (links intactos)', () => {
    expect(INDEX_SOURCE).toMatch(/lg:grid-cols-\[264px_1fr\]/);
    expect(INDEX_SOURCE).toMatch(/categories=\{filterCategoryOptions\}/);
    expect(INDEX_SOURCE).toMatch(/prices=\{filterPriceOptions\}/);
    expect(INDEX_SOURCE).toMatch(/colors=\{filterColorOptions\}/);
    expect(INDEX_SOURCE).toMatch(/clearHref=\{baseUrl\}/);
  });
});
