/**
 * Tests estructurales de FiltersDisclosure.astro (drawer de filtros en
 * móvil — WU3 de catalogo-plp-busqueda).
 *
 * Patrón de WU2: los `.astro` no se renderizan en jsdom → source-matching
 * estructural (readFileSync + regex), igual que `FilterChips.test.ts` y
 * `CatalogStateMessage.test.ts`. La integración en la página vive en
 * `catalog-plp-filters-mobile.test.ts`.
 *
 * Contrato del componente:
 *  - `<details>`/`<summary>` nativo (disclosure sin JS, teclado gratis).
 *  - Trigger compacto sólo < lg; el sidebar estático sigue igual ≥ lg.
 *  - El contenido revelado es el MISMO `FiltersSidebar` (links +
 *    `aria-current` intactos), con `idPrefix` distinto por copia para
 *    no duplicar ids.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const COMPONENT_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/FiltersDisclosure.astro',
);
const SIDEBAR_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/FiltersSidebar.astro',
);
const SOURCE = readFileSync(COMPONENT_PATH, 'utf-8');
const SIDEBAR_SOURCE = readFileSync(SIDEBAR_PATH, 'utf-8');

describe('FiltersDisclosure — disclosure nativo sin JS', () => {
  it('usa <details>/<summary> y ningún script ni handler inline', () => {
    expect(SOURCE).toMatch(/<details\b/);
    expect(SOURCE).toMatch(/<summary\b/);
    expect(SOURCE).not.toMatch(/<script/i);
    expect(SOURCE).not.toMatch(/onclick/i);
    expect(SOURCE).not.toMatch(/addEventListener/i);
    expect(SOURCE).not.toMatch(/client:/);
  });

  it('el trigger es operable por teclado con estados hover/focus-visible', () => {
    expect(SOURCE).toMatch(/hover:/);
    expect(SOURCE).toMatch(/focus-visible:/);
    // El marker default del summary se oculta; el chevron es propio.
    expect(SOURCE).toMatch(/list-none/);
    expect(SOURCE).toMatch(/::-webkit-details-marker|marker:/);
    // El chevron acompaña el estado open sin JS (group-open).
    expect(SOURCE).toMatch(/group-open:/);
  });
});

describe('FiltersDisclosure — responsive < lg / ≥ lg', () => {
  it('el disclosure vive sólo en móvil (lg:hidden)', () => {
    expect(SOURCE).toMatch(/<details[^>]*class="[^"]*lg:hidden/);
  });

  it('el sidebar estático ≥ lg se conserva (hidden lg:block + sticky)', () => {
    expect(SOURCE).toMatch(/hidden lg:block/);
    expect(SOURCE).toMatch(/sticky top-24/);
  });

  it('renderiza FiltersSidebar en el disclosure y en el panel estático', () => {
    const instances = SOURCE.match(/<FiltersSidebar\b/g) ?? [];
    expect(instances.length).toBe(2);
    // Copias con ids únicos: nada de ids duplicados en el DOM.
    expect(SOURCE).toMatch(/idPrefix="m-"/);
    expect(SOURCE).toMatch(/idPrefix="d-"/);
  });

  it('FiltersSidebar acepta idPrefix y compone sus ids de heading con él', () => {
    expect(SIDEBAR_SOURCE).toMatch(/idPrefix\?:/);
    expect(SIDEBAR_SOURCE).toMatch(/\$\{idPrefix\}filter-category-heading/);
  });
});

describe('FiltersDisclosure — trigger con conteo de filtros activos', () => {
  it('muestra label simple o plantilla con {count} según los filtros activos', () => {
    expect(SOURCE).toMatch(/triggerLabel/);
    expect(SOURCE).toMatch(/triggerCountTemplate/);
    expect(SOURCE).toMatch(/activeCount/);
    expect(SOURCE).toMatch(/activeCount > 0/);
    expect(SOURCE).toMatch(/\.replace\('\{count\}'/);
  });
});

describe('FiltersDisclosure — paleta PLP (slate, sin tokens nuevos)', () => {
  it('repite el lenguaje visual del card de filtros existente', () => {
    expect(SOURCE).toMatch(/rounded-2xl/);
    expect(SOURCE).toMatch(/border-slate-200/);
    expect(SOURCE).toMatch(/bg-white/);
  });

  it('no introduce islas, overlays ni colores fuera del slate/primary', () => {
    expect(SOURCE).not.toMatch(/fixed inset-0/);
    expect(SOURCE).not.toMatch(/z-\[?[45]\d\d/);
  });
});
