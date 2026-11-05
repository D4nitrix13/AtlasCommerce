/**
 * T3.4 — acabados: chips de color hex (no miniaturas que parecen
 * imágenes repetidas) + wiring de preview de imagen en hover.
 *
 * Source scans sobre [slug].astro (mismo estilo que
 * pdp-tabs-placement.test.ts). Cobertura:
 *   - Los swatches del picker son spans de color hex — nunca `<img>`:
 *     los thumbnails a 48px "parecen imágenes repetidas" (usuario).
 *   - Cada swatch expone `data-finish-preview` (imagen de la variante).
 *   - El preview responde a hover + focus y restaura al salir, y
 *     reemplaza también el `srcset` (con srcset presente el browser
 *     ignora el cambio de src y el preview no se ve).
 *   - La navegación `?finish=` (click) se preserva.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SLUG_PAGE_PATH = resolve(
  __dirname,
  '../../../../../src/pages/[locale]/products/[slug].astro',
);

const SOURCE = readFileSync(SLUG_PAGE_PATH, 'utf-8');

/** Bloque `<dt>Acabado</dt><dd>…</dd>` del selector de acabado. */
function extractAcabadoBlock(source: string): string {
  const start = source.indexOf('Acabado</dt>');
  expect(start).toBeGreaterThanOrEqual(0);
  const end = source.indexOf('</dd>', start);
  expect(end).toBeGreaterThan(start);
  return source.slice(start, end);
}

/** El `<ul>` del picker (swatches navegables). */
function extractPickerBlock(source: string): string {
  const start = source.indexOf('aria-label="Selector de acabado"');
  expect(start).toBeGreaterThanOrEqual(0);
  const end = source.indexOf('</ul>', start);
  expect(end).toBeGreaterThan(start);
  return source.slice(start, end);
}

describe('selector de acabado del PDP', () => {
  const acabadoBlock = extractAcabadoBlock(SOURCE);
  const pickerBlock = extractPickerBlock(SOURCE);

  it('los swatches son spans de color hex, sin <img> en el bloque ACABADO', () => {
    // El usuario: las miniaturas 48px "parecen imágenes repetidas".
    // El acabado se comunica con su hex; sin hex, color de fallback.
    expect(acabadoBlock).not.toMatch(/<img\b/);
    expect(acabadoBlock).toMatch(/background-color/);
  });

  it('expone data-finish-preview para el preview en hover/focus', () => {
    expect(pickerBlock).toContain('data-finish-preview');
  });

  it('el picker mantiene la navegación ?finish= en el click', () => {
    expect(pickerBlock).toMatch(/href=/);
    expect(pickerBlock).toMatch(/\?finish=/);
  });

  it('el swatch activo mantiene ring claro y el hover agrega ring', () => {
    expect(pickerBlock).toMatch(/hover:ring-2/);
    expect(pickerBlock).toMatch(/ring-2/);
  });

  it('el preview responde a hover y focus (y restaura al salir)', () => {
    expect(SOURCE).toMatch(/function setupFinishPreview/);
    expect(SOURCE).toMatch(/addEventListener\('mouseenter'/);
    expect(SOURCE).toMatch(/addEventListener\('mouseleave'/);
    expect(SOURCE).toMatch(/addEventListener\('focus'/);
    expect(SOURCE).toMatch(/addEventListener\('blur'/);
  });

  it('el preview reemplaza también el srcset (si no, el browser ignora el src)', () => {
    // La galería renderiza <img srcset=…>: con srcset presente el
    // browser elige del srcset e ignora el cambio de src — el
    // preview no se vería. Hay que quitarlo y restaurarlo.
    expect(SOURCE).toMatch(/removeAttribute\('srcset'\)/);
    expect(SOURCE).toMatch(/setAttribute\('srcset'/);
  });
});
