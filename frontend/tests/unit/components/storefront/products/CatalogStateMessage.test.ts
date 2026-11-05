/**
 * Tests estructurales de CatalogStateMessage.astro (tarjeta de estado
 * del PLP: error / vacío / categoría sin resultados — vista 04, WU2 de
 * catalogo-plp-busqueda).
 *
 * Los componentes Astro no se renderizan en jsdom (requieren el
 * compilador Astro) → tests estructurales sobre el source, mismo
 * patrón que `FilterChips.test.ts`. La integración en la página vive
 * en `catalog-plp-states.test.ts`.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const COMPONENT_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/CatalogStateMessage.astro',
);
const SOURCE = readFileSync(COMPONENT_PATH, 'utf-8');

describe('CatalogStateMessage — shape estructural', () => {
  it('renderiza el title obligatorio y el hint sólo si viene', () => {
    expect(SOURCE).toMatch(/\{title\}/);
    expect(SOURCE).toMatch(/hint\?:/);
    expect(SOURCE).toMatch(/hint\s*&&/);
  });

  it('el link opcional es un <a href> nativo, sin JS ni <button>', () => {
    expect(SOURCE).toMatch(/<a\s[^>]*href=\{linkHref\}/);
    expect(SOURCE).toMatch(/linkHref\?:/);
    expect(SOURCE).toMatch(/linkLabel\?:/);
    expect(SOURCE).not.toMatch(/<script/i);
    expect(SOURCE).not.toMatch(/<button/i);
    expect(SOURCE).not.toMatch(/onclick/i);
  });

  it('usa la paleta del empty state existente del PLP (paper/ink/line)', () => {
    expect(SOURCE).toMatch(/rounded-2xl/);
    expect(SOURCE).toMatch(/border-\[var\(--line/);
    expect(SOURCE).toMatch(/bg-\[var\(--paper/);
    expect(SOURCE).toMatch(/text-\[var\(--ink/);
  });

  it('el link tiene estados hover/focus-visible y área de toque cómoda', () => {
    expect(SOURCE).toMatch(/hover:/);
    expect(SOURCE).toMatch(/focus-visible:/);
    expect(SOURCE).toMatch(/min-h-(8|9|10)/);
  });
});
