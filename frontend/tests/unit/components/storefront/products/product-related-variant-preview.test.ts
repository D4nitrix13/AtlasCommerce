/**
 * Tests estructurales del preview de variante en ProductRelated (lote 2, T3.4).
 *
 * Source scans (los .astro no se renderizan en jsdom). Cobertura:
 *   - Cada imagen del stack se etiqueta con su `data-variant-id` real
 *     (también la primera: antes estaba hardcodeada `"default"` y el
 *     primer swatch no matcheaba ninguna imagen).
 *   - Las reglas de opacidad del preview viven en el CSS generado
 *     global (`buildVariantPreviewCss`), NO en el `<style>` scoped:
 *     Astro agrega `[data-astro-cid]` a los selectores scoped y eso
 *     les ganaba en especificidad a las reglas `is:global`, dejando
 *     la imagen preview siempre en `opacity: 0`.
 *   - El contrato `setupVariantPreview` (mouseenter/leave + focus/blur
 *     sobre `data-variant-swatch`) sigue cableado.
 *   - Los swatches pintan su hex con `swatchFallbackHex` como último
 *     recurso (no el gris mágico '#888').
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const RELATED_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductRelated.astro',
);

const SOURCE = readFileSync(RELATED_PATH, 'utf-8');

describe('ProductRelated — preview de variante en hover/focus del swatch', () => {
  it('cada imagen del stack se etiqueta con su data-variant-id real (también la primera)', () => {
    expect(SOURCE).not.toMatch(/data-variant-id="default"/);
    expect(SOURCE).not.toMatch(/variantImages\.slice\(1\)/);
    expect(SOURCE).toMatch(/data-variant-id=\{img\.id\}/);
  });

  it('las reglas de opacidad del preview viven en el CSS global, no en el <style> scoped', () => {
    const scopedStart = SOURCE.indexOf('\n<style>');
    expect(scopedStart).toBeGreaterThanOrEqual(0);
    const scopedEnd = SOURCE.indexOf('</style>', scopedStart);
    const scoped = SOURCE.slice(scopedStart, scopedEnd);
    expect(scoped).not.toMatch(/card__img/);
    expect(scoped).not.toMatch(/data-active-variant/);
    expect(SOURCE).toMatch(/buildVariantPreviewCss\(/);
    expect(SOURCE).toMatch(/<style is:global set:html=\{variantPreviewCss\}/);
  });

  it('el contrato setupVariantPreview sigue cableado (mouseenter/leave + focus/blur)', () => {
    expect(SOURCE).toMatch(/data-variant-swatch=/);
    expect(SOURCE).toMatch(/data-active-variant/);
    expect(SOURCE).toMatch(/function setupVariantPreview/);
    for (const eventName of ['mouseenter', 'mouseleave', 'focus', 'blur'] as const) {
      expect(SOURCE).toMatch(new RegExp(`addEventListener\\('${eventName}'`));
    }
  });

  it('los swatches pintan hex propio con swatchFallbackHex como último recurso', () => {
    expect(SOURCE).toMatch(/swatchFallbackHex/);
    expect(SOURCE).not.toMatch(/'#888'/);
  });
});
