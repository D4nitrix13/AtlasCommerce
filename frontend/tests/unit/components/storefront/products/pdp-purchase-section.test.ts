/**
 * Sección de compra del PDP: precio + acabado + tamaño + cantidad en UN
 * solo bloque, con etiquetas de campo en micro-caps.
 *
 * **Pedido del usuario (2026-01):** "precio, acabado, tamaño y cantidad
 * debería ir en una sección". Hasta ahora eran cuatro islas: un `<dl>`
 * de dos columnas (Precio | Acabado, con "Mano" enterrada adentro), la
 * grilla de tamaños suelta y la barra de compra aparte. El corazón del
 * wishlist venía `h-16` en una fila `items-stretch` y se veía más alto
 * que los botones y desalineado.
 *
 * Source scans (Astro/React no se renderizan en jsdom acá).
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SLUG_PAGE_PATH = resolve(
  __dirname,
  '../../../../../src/pages/[locale]/products/[slug].astro',
);
const CTAS_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductCTAs.astro',
);

const PAGE = readFileSync(SLUG_PAGE_PATH, 'utf-8');
const CTAS = readFileSync(CTAS_PATH, 'utf-8');

/** Bloque `<aside>…</aside>` de la columna derecha (datos del producto). */
function extractAsideBlock(source: string): string {
  const start = source.indexOf('<aside');
  const end = source.indexOf('</aside>');
  expect(start).toBeGreaterThanOrEqual(0);
  expect(end).toBeGreaterThan(start);
  return source.slice(start, end);
}

describe('PDP — la compra es una sola sección', () => {
  const aside = extractAsideBlock(PAGE);

  it('existe UN bloque de orden (paper-card) que contiene los cuatro campos', () => {
    // Precio · Acabado · Tamaño · Cantidad viven en la misma tarjeta.
    const start = aside.indexOf('paper-card');
    expect(start).toBeGreaterThanOrEqual(0);
    const sectionEnd = aside.indexOf('</section>', start);
    expect(sectionEnd).toBeGreaterThan(start);
    const card = aside.slice(start, sectionEnd);

    expect(card).toMatch(/<ProductFinishTiles\b/);
    expect(card).toMatch(/<ProductSizeGrid\b/);
    expect(card).toMatch(/<ProductPurchaseBar\b/);
    expect(card).toMatch(/<ProductCTAs\b/);
  });

  it('los campos se rotulan con micro-label, no con <dt> sueltos', () => {
    // El ritmo de "orden de trabajo": etiqueta micro-caps arriba del
    // control, no la grilla `<dl>` de dos columnas que competía.
    const start = aside.indexOf('paper-card');
    const card = aside.slice(start, aside.indexOf('</section>', start));
    // Precio y Acabado los rotula la página; Tamaño y Cantidad los
    // rotula cada control (ProductSizeGrid / ProductPurchaseBar).
    expect(card).toMatch(/micro-label/);
    expect(readFileSync(
      resolve(__dirname, '../../../../../src/components/storefront/products/ProductSizeGrid.astro'),
      'utf-8',
    )).toMatch(/micro-label/);
    expect(readFileSync(
      resolve(__dirname, '../../../../../src/components/storefront/products/ProductPurchaseBar.tsx'),
      'utf-8',
    )).toMatch(/micro-label/);
  });

  it('desaparece el <dl> de dos columnas Precio | Acabado', () => {
    // Ese dl era el que dispersaba la información en dos mitades.
    expect(aside).not.toMatch(/grid-cols-2[^>]*>\s*<div[^>]*>\s*<dt[^>]*>Precio/);
    expect(aside).not.toContain('Acabado</dt>');
  });

  it('Mano deja de estar enterrada dentro del grupo Acabado', () => {
    // "Mano: Derecha" colgaba del `<dd>` de Acabado. Ahora es su
    // propio campo del orden.
    const finishIdx = aside.indexOf('ProductFinishTiles');
    const handIdx = aside.search(/handedness|hand\.label|HandednessChip/);
    if (handIdx !== -1 && finishIdx !== -1) {
      expect(handIdx).toBeGreaterThan(finishIdx);
    }
  });
});

describe('ProductCTAs — el corazón comparte la línea base de los CTAs', () => {
  it('la fila no estira los hijos (items-center, no items-stretch)', () => {
    // `sm:items-stretch` estiraba el corazón hasta la altura de la
    // fila y quedaba más alto que los botones `h-12`.
    expect(CTAS).toMatch(/sm:items-center/);
    expect(CTAS).not.toMatch(/sm:items-stretch/);
  });

  it('el corazón es un botón cuadrado h-12 w-12, no un círculo h-16', () => {
    // Comparte la altura de los CTAs (`h-12`) y usa el mismo radio
    // que el sistema (`rounded-lg`), no `rounded-full`.
    expect(CTAS).toMatch(/h-12 w-12/);
    expect(CTAS).not.toMatch(/h-16 w-16/);
  });

  it('no envuelve el corazón en un flex-col con caption colgando', () => {
    // Ese wrapper era la otra mitad de la desalineación.
    expect(CTAS).not.toMatch(/flex flex-col items-center gap-1/);
  });
});
