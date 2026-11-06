/**
 * Tests estructurales de ubicación de las tabs del PDP (lote 2, T3.1).
 *
 * Los componentes Astro no se renderizan en jsdom (requieren el
 * compilador Astro) → tests estructurales sobre el source
 * (estilo `catalog-grid-css.test.ts`).
 *
 * Cobertura:
 *   - `ProductDetailTabs` se renderiza DENTRO del `<aside>` de datos
 *     del producto (columna derecha del grid), no como bloque aparte.
 *   - No queda un bloque standalone de tabs después de que cierra el
 *     grid (un solo `<ProductDetailTabs` en la página).
 *   - La descripción suelta duplicada (`ProductDetailsBlock` inline) y
 *     la línea de envíos suelta salen de la columna: su contenido vive
 *     en los paneles de las tabs.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SLUG_PAGE_PATH = resolve(
  __dirname,
  '../../../../../src/pages/[locale]/products/[slug].astro',
);

const SOURCE = readFileSync(SLUG_PAGE_PATH, 'utf-8');

/** Bloque `<aside>…</aside>` de la columna derecha (datos del producto). */
function extractAsideBlock(source: string): string {
  const start = source.indexOf('<aside');
  const end = source.indexOf('</aside>');
  expect(start).toBeGreaterThanOrEqual(0);
  expect(end).toBeGreaterThan(start);
  return source.slice(start, end);
}

describe('PDP — tabs dentro de la sección de datos del producto', () => {
  const asideBlock = extractAsideBlock(SOURCE);

  it('ProductDetailTabs se renderiza dentro del <aside> (columna de datos)', () => {
    // El usuario pidió que las tabs vivan en la misma sección donde
    // están los datos del producto ("aprovechar cada espacio"), no
    // como bloque full-width detached bajo el grid.
    expect(asideBlock).toMatch(/<ProductDetailTabs\b/);
  });

  it('no queda un bloque standalone de tabs después del grid', () => {
    // Un solo render en toda la página, y dentro del aside: si
    // volviera a aparecer después de `</section>` del grid, el
    // conteo o la ubicación fallan.
    const occurrences = SOURCE.match(/<ProductDetailTabs\b/g) ?? [];
    expect(occurrences).toHaveLength(1);

    const afterAside = SOURCE.slice(SOURCE.indexOf('</aside>'));
    expect(afterAside).not.toMatch(/<ProductDetailTabs\b/);
  });

  it('la descripción suelta duplicada sale de la columna de datos', () => {
    // `ProductDetailsBlock` inline duplicaba el panel Descripción de
    // las tabs. El contenido vive en el panel; el bloque suelto se va.
    expect(asideBlock).not.toMatch(/<ProductDetailsBlock\b/);
    expect(SOURCE).not.toMatch(/<ProductDetailsBlock\b/);
  });

  it('la línea de envíos suelta sale de la columna de datos', () => {
    // El párrafo de envíos duplicaba el panel Envíos de las tabs.
    expect(asideBlock).not.toMatch(
      /<p[^>]*>\s*\{t\['storefront\.products\.detail\.v4\.shippingInline'\]\}/,
    );
  });
});
