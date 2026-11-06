/**
 * Tests de los helpers puros del preview de acabados (lote 2, T3.4).
 *
 * Cobertura:
 *   - `resolveFinishPreviewImage`: resolución de la imagen de preview
 *     por acabado (galería del PDP al hacer hover en el swatch).
 *   - `buildVariantPreviewCss`: CSS de cross-fade por variante de las
 *     cards de rieles. Las reglas de show deben ganarle a las de hide
 *     en especificidad/cascade (bug real: el `<style>` scoped de Astro
 *     les ganaba a las reglas `is:global` y el preview nunca se veía).
 *     La primaria se marca con clase `.card__img--default` en el markup
 *     (no con un selector `:not([data-active-variant])` por id) para
 *     que dos cards con ids de variante iguales no compartan reglas.
 */
import { describe, expect, it } from 'vitest';
import { buildVariantPreviewCss } from '../../../src/lib/product-card-swap';
import { resolveFinishPreviewImage } from '../../../src/lib/product-variant-selection';

describe('resolveFinishPreviewImage', () => {
  it('devuelve la URL de la variante cuando existe', () => {
    expect(resolveFinishPreviewImage('sunburst', { sunburst: '/img/a.webp' })).toBe('/img/a.webp');
  });

  it('devuelve undefined para un acabado sin imagen', () => {
    expect(resolveFinishPreviewImage('black', { sunburst: '/img/a.webp' })).toBeUndefined();
  });

  it('trata el string vacío como sin imagen', () => {
    expect(resolveFinishPreviewImage('black', { black: '' })).toBeUndefined();
  });
});

describe('buildVariantPreviewCss', () => {
  const css = buildVariantPreviewCss(['sunburst', 'black']);

  it('oculta todas las variantes por defecto y muestra la primaria sin hover', () => {
    expect(css).toContain('.card__img--variant{opacity:0;');
    expect(css).toContain('.card__img--default{opacity:1;');
  });

  it('muestra la variante activa cuando hay data-active-variant', () => {
    expect(css).toContain('article[data-active-variant] .card__img--variant{opacity:0;}');
    expect(css).toContain('article[data-active-variant] .card__img--default{opacity:0;}');
    expect(css).toContain(
      'article[data-active-variant="black"] .card__img--variant[data-variant-id="black"]{opacity:1;}',
    );
    expect(css).toContain(
      'article[data-active-variant="sunburst"] .card__img--variant[data-variant-id="sunburst"]{opacity:1;}',
    );
  });

  it('las reglas de show van después del hide para que el cascade resuelva', () => {
    const baseHide = css.indexOf('.card__img--variant{opacity:0;');
    const primaryShow = css.indexOf('.card__img--default{opacity:1;');
    const hideIndex = css.indexOf('article[data-active-variant] .card__img--variant{opacity:0;}');
    const showIndex = css.indexOf('article[data-active-variant="black"]');
    expect(primaryShow).toBeGreaterThan(baseHide);
    expect(hideIndex).toBeGreaterThanOrEqual(0);
    expect(showIndex).toBeGreaterThan(hideIndex);
  });
});
