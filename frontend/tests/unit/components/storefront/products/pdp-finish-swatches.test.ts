/**
 * Acabados del PDP: fichas rectangulares con imagen (referencia Fender).
 *
 * **Decisión revertida a pedido explícito del usuario (2026-01).** La
 * versión anterior (commit 49c55c3b) codificaba "puntos de color hex,
 * nunca `<img>`" porque las miniaturas de 48px "parecían imágenes
 * repetidas". El usuario ahora quiere tiles rectangulares con la foto
 * del producto, como muestra Fender: la ficha ES la muestra del
 * sustrato en un taller de estampado.
 *
 * Source scans sobre `ProductFinishTiles.astro` (Astro no se renderiza
 * en jsdom — mismo estilo que `pdp-tabs-placement.test.ts`).
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const TILES_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductFinishTiles.astro',
);

const SOURCE = readFileSync(TILES_PATH, 'utf-8');

describe('ProductFinishTiles — fichas rectangulares de acabado', () => {
  it('cada ficha es rectangular (rounded-lg) con tamaño fijo', () => {
    // La ficha es una tarjeta de muestra, no un punto de color:
    // `rounded-lg` y un tamaño fijo para que la fila de muestras se
    // lea como el panel de acabados de Fender.
    expect(SOURCE).toMatch(/rounded-lg/);
    expect(SOURCE).toMatch(/\bsize-(16|20)\b|w-(16|20)\b/);
  });

  it('la ficha muestra la imagen del producto en un <img>', () => {
    // Decisión revertida: ahora SÍ queremos la foto de la variante.
    expect(SOURCE).toMatch(/<img\b/);
    expect(SOURCE).toMatch(/object-cover/);
  });

  it('sin imagen de variante, cae a relleno con el hex del acabado', () => {
    // Un acabado sin foto no debe romper la fila: se pinta su hex.
    expect(SOURCE).toMatch(/background-color/);
  });

  it('expone data-finish-preview para el preview en hover/focus', () => {
    expect(SOURCE).toContain('data-finish-preview');
  });

  it('la navegación ?finish= se preserva y funciona sin JavaScript', () => {
    // El click es un <a> real: sin JS el deep-link sigue filtrando.
    expect(SOURCE).toMatch(/href=\{/);
    expect(SOURCE).toMatch(/\?finish=/);
  });

  it('la ficha activa se marca con ring de acento y aria-current', () => {
    expect(SOURCE).toMatch(/ring-2/);
    expect(SOURCE).toMatch(/var\(--primary/);
    expect(SOURCE).toMatch(/aria-current/);
  });

  it('cada ficha lleva su etiqueta en micro-label', () => {
    // El chip de muestra se lee como Pantone: muestrario + nombre.
    expect(SOURCE).toMatch(/micro-label/);
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
