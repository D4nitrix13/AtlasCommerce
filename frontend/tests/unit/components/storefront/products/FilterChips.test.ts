/**
 * Tests estructurales de FilterChips.astro (chips de filtros activos
 * del PLP, vista 04) + su integración en `products/index.astro`.
 *
 * Los componentes Astro no se renderizan en jsdom (requieren el
 * compilador Astro) → tests estructurales sobre el source, mismo
 * patrón que `catalog-grid-css.test.ts` y `product-faq.test.ts`. La
 * lógica de QUÉ chips renderizar vive testeada en
 * `tests/unit/lib/catalog-filters.test.ts` (`buildActiveFilterChips`).
 *
 * Cobertura:
 *   - Empty-safe: sin chips activos no se renderiza nada.
 *   - Cada chip es un `<a href>` nativo (sin JS, sin `<script>`).
 *   - Afortance de remoción (×) con aria-label i18n `{label}`.
 *   - Fila con wrap + área de toque cómoda + hover/focus-visible.
 *   - `index.astro` importa y renderiza FilterChips.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const FILTER_CHIPS_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/FilterChips.astro',
);
const INDEX_PATH = resolve(
  __dirname,
  '../../../../../src/pages/[locale]/products/index.astro',
);

const FILTER_CHIPS_SOURCE = readFileSync(FILTER_CHIPS_PATH, 'utf-8');
const INDEX_SOURCE = readFileSync(INDEX_PATH, 'utf-8');

describe('FilterChips — shape estructural', () => {
  it('es empty-safe: no renderiza nada sin chips activos', () => {
    expect(FILTER_CHIPS_SOURCE).toMatch(/chips\.length\s*>\s*0/);
  });

  it('cada chip es un <a href> nativo, sin JS ni <script>', () => {
    expect(FILTER_CHIPS_SOURCE).toMatch(/<a\s[^>]*href=\{chip\.href\}/);
    expect(FILTER_CHIPS_SOURCE).not.toMatch(/<script/i);
    expect(FILTER_CHIPS_SOURCE).not.toMatch(/<button/i);
  });

  it('el affordance de remoción (×) es aria-hidden y el link lleva aria-label i18n', () => {
    expect(FILTER_CHIPS_SOURCE).toMatch(/aria-hidden="true"/);
    expect(FILTER_CHIPS_SOURCE).toMatch(/×/);
    expect(FILTER_CHIPS_SOURCE).toMatch(
      /aria-label=\{[^}]*replace\('\{label\}',\s*chip\.label\)\}/,
    );
  });

  it('la fila de chips usa wrap, área de toque cómoda y estados hover/focus-visible', () => {
    expect(FILTER_CHIPS_SOURCE).toMatch(/flex-wrap/);
    expect(FILTER_CHIPS_SOURCE).toMatch(/min-h-(8|9|10)/);
    expect(FILTER_CHIPS_SOURCE).toMatch(/hover:/);
    expect(FILTER_CHIPS_SOURCE).toMatch(/focus-visible:/);
  });
});

describe('products/index.astro — integración de FilterChips', () => {
  it('importa FilterChips y lo renderiza', () => {
    expect(INDEX_SOURCE).toMatch(
      /import FilterChips from '\.\.\/\.\.\/\.\.\/components\/storefront\/products\/FilterChips\.astro'/,
    );
    expect(INDEX_SOURCE).toMatch(/<FilterChips\b/);
  });
});
