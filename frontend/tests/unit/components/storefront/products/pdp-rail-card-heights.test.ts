/**
 * Tests estructurales de altura pareja en los rieles del PDP (lote 2, T3.3).
 *
 * Los componentes Astro no se renderizan en jsdom (requieren el
 * compilador Astro) → tests estructurales sobre el source
 * (estilo `catalog-grid-css.test.ts`).
 *
 * Cobertura:
 *   - `ProductRelated.astro` (También Te Puede Gustar): `h-full` en el
 *     `<article>` de la card y en su `<li>`, footer (precio) con
 *     `mt-auto`, y media `object-cover` (sin `object-contain`: contain
 *     deja huecos cuando la foto no es cuadrada).
 *   - `ProductFrequentlyBoughtTogether.astro` y
 *     `ProductRecentlyViewed.astro`: mismas reglas de altura/estirado
 *     y media `object-cover` por consistencia.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const PRODUCTS_DIR = resolve(__dirname, '../../../../../src/components/storefront/products');

const RELATED = readFileSync(resolve(PRODUCTS_DIR, 'ProductRelated.astro'), 'utf-8');
const FBT = readFileSync(
  resolve(PRODUCTS_DIR, 'ProductFrequentlyBoughtTogether.astro'),
  'utf-8',
);
const RECENT = readFileSync(resolve(PRODUCTS_DIR, 'ProductRecentlyViewed.astro'), 'utf-8');

/** Bloque `<li>…</article></li>` de una card de riel en ProductRelated. */
function extractRelatedCard(source: string): string {
  const start = source.indexOf('<li class="w-');
  expect(start).toBeGreaterThanOrEqual(0);
  const end = source.indexOf('</article>', start);
  expect(end).toBeGreaterThan(start);
  return source.slice(start, end);
}

describe('ProductRelated — cards de la misma altura (También Te Puede Gustar)', () => {
  const card = extractRelatedCard(RELATED);

  it('el <article> de la card tiene h-full para estirar al alto del row', () => {
    // Sin h-full la card crece solo con su contenido: una con badges +
    // swatches + título de 2 líneas queda más alta que sus vecinas.
    // El contenedor flex del riel ya estira los <li>; el article debe
    // llenar ese alto (patrón ProductCard.astro).
    expect(card).toMatch(/<article[^>]*\bh-full\b/);
  });

  it('el <li> de la card tiene h-full (stretch chain)', () => {
    // El stretch del flex row da la altura al <li>; h-full hace la
    // cadena explícita para que el <article> pueda resolver su 100%.
    expect(card).toMatch(/<li[^>]*\bh-full\b/);
  });

  it('mantiene flex-col en el article para que el footer baje con mt-auto', () => {
    expect(card).toMatch(/<article[^>]*\bflex[^>]*\bflex-col/);
  });

  it('el footer de precio usa mt-auto (anclado al fondo de la card)', () => {
    // Precio como ancla inferior: con filas opcionales (badges,
    // swatches, "N acabados") distintas entre cards, mt-auto empuja
    // el footer al fondo y todas escanean como una fila pareja.
    expect(card).toMatch(/class="[^"]*\bmt-auto\b[^"]*"[^>]*>[\s\S]*?formatCurrency/);
  });

  it('las imágenes del riel usan object-cover (sin huecos)', () => {
    // object-contain letterboxea (huecos) cuando la foto no es
    // cuadrada; object-cover escala proporcional y recorta bordes:
    // sin estirar, sin deformar, sin huecos.
    expect(RELATED).not.toMatch(/object-contain/);
    expect(card).toMatch(/class="[^"]*\bobject-cover\b/);
  });
});

describe('ProductFrequentlyBoughtTogether — alturas parejas', () => {
  it('el <li> de la card tiene h-full para estirar al row', () => {
    expect(FBT).toMatch(/<li[^>]*\bh-full\b/);
  });

  it('el bloque de precio usa mt-auto (anclado al fondo)', () => {
    expect(FBT).toMatch(/class="[^"]*\bmt-auto\b[^"]*"[^>]*>[\s\S]*?formatCurrency/);
  });

  it('la imagen usa object-cover', () => {
    expect(FBT).toMatch(/class="[^"]*\bobject-cover\b/);
    expect(FBT).not.toMatch(/object-contain/);
  });
});

describe('ProductRecentlyViewed — alturas parejas', () => {
  it('el <li> y el <a> de la card usan h-full (SSR y template JS)', () => {
    // El grid CSS ya estira los <li> al row; el <a> interno debe
    // llenar ese alto para que el precio baje con mt-auto. La card
    // vive dos veces: render SSR y template del fallback por JS.
    const liHFull = RECENT.match(/<li[^>]*\bh-full\b/g) ?? [];
    const aHFull = RECENT.match(/<a[^>]*\bh-full\b/g) ?? [];
    expect(liHFull.length).toBeGreaterThanOrEqual(2);
    expect(aHFull.length).toBeGreaterThanOrEqual(2);
  });

  it('el precio usa mt-auto en SSR y en el template JS', () => {
    const mtAutoCount = RECENT.match(/\bmt-auto\b/g) ?? [];
    expect(mtAutoCount.length).toBeGreaterThanOrEqual(2);
  });

  it('la imagen usa object-cover', () => {
    expect(RECENT).toMatch(/class="[^"]*\bobject-cover\b/);
    expect(RECENT).not.toMatch(/object-contain/);
  });
});
