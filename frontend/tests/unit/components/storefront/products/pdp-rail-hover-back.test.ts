/**
 * Tests estructurales del hover → imagen trasera en las cards de los
 * rieles del PDP (lote 2, T3.5).
 *
 * Source-scan (los componentes Astro no se renderizan en jsdom), mismo
 * estilo que `pdp-rail-card-heights.test.ts`.
 *
 * Cobertura:
 *   - `ProductRelated.astro` (+ su media stack extraído): `<img
 *     data-role="back">` alimentado por `imageUrlSecondary`, oculto por
 *     defecto, revelado en hover/focus-within del media, y SIEMPRE
 *     subordinado al preview de variante (`:not([data-active-variant])`).
 *   - `ProductFrequentlyBoughtTogether.astro` y
 *     `ProductRecentlyViewed.astro` (grid SSR + template del fallback
 *     JS): mismo cableado cuando el dato trae imagen secundaria; sin
 *     ella, cero cambio de comportamiento.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const PRODUCTS_DIR = resolve(__dirname, '../../../../../src/components/storefront/products');

function readSource(name: string): string {
  return readFileSync(resolve(PRODUCTS_DIR, name), 'utf-8');
}

/** El media stack de ProductRelated vive en su subcomponente (límite
 *  de líneas); el scan une ambos para no duplicar aserciones. */
function readRelatedStack(): string {
  let media = '';
  try {
    media = readSource('ProductRelatedMedia.astro');
  } catch {
    /* subcomponente aún no extraído: el scan cubre el inline */
  }
  return `${readSource('ProductRelated.astro')}\n${media}`;
}

const RELATED_STACK = readRelatedStack();
const FBT = readSource('ProductFrequentlyBoughtTogether.astro');
const RECENT = readSource('ProductRecentlyViewed.astro');

/** Reglas CSS cuyo selector menciona la imagen back (auditoría de selectores). */
function backRules(source: string): string[] {
  return source.match(/[^{}]*card__img--back[^{}]*\{[^}]*\}/g) ?? [];
}

describe('ProductRelated — hover muestra la imagen trasera (T3.5)', () => {
  it('el media stack emite <img data-role="back"> alimentado por imageUrlSecondary', () => {
    expect(RELATED_STACK).toMatch(/data-role="back"/);
    expect(RELATED_STACK).toMatch(/imageUrlSecondary/);
  });

  it('la imagen back sólo se renderiza cuando imageUrlSecondary existe', () => {
    // Sin hover image no hay <img data-role="back"> en el DOM y la
    // card queda con su sola imagen primaria (cero cambio).
    expect(RELATED_STACK).toMatch(/imageUrlSecondary[\s\S]{0,240}data-role="back"/);
  });

  it('el back queda oculto por defecto (opacity: 0) para no tapar la primaria', () => {
    expect(RELATED_STACK).toMatch(/card__img--back[^}]*opacity:\s*0/);
  });

  it('el reveal en hover está acotado a :not([data-active-variant]) — el preview de swatch gana', () => {
    // Precedencia: cuando un swatch dejó data-active-variant en el
    // <article>, la foto de ESA variante manda y el back no puede
    // taparla. Cada regla que revela el back en hover debe llevar el
    // guard :not([data-active-variant]).
    expect(RELATED_STACK).toMatch(/:not\(\[data-active-variant\]\)/);
    const revealRules = backRules(RELATED_STACK).filter((rule) => /opacity:\s*1/.test(rule));
    expect(revealRules.length).toBeGreaterThan(0);
    expect(revealRules.every((rule) => rule.includes(':not([data-active-variant])'))).toBe(true);
    expect(revealRules.some((rule) => rule.includes(':hover'))).toBe(true);
  });

  it('focus-within en el media link revela el back (teclado)', () => {
    const focusRules = backRules(RELATED_STACK).filter(
      (rule) => rule.includes('focus-within') && /opacity:\s*1/.test(rule),
    );
    expect(focusRules.length).toBeGreaterThan(0);
    expect(focusRules.every((rule) => rule.includes(':not([data-active-variant])'))).toBe(true);
  });
});

describe('ProductFrequentlyBoughtTogether — hover muestra la imagen trasera (T3.5)', () => {
  it('emite <img data-role="back"> alimentado por imageUrlSecondary', () => {
    expect(FBT).toMatch(/data-role="back"/);
    expect(FBT).toMatch(/imageUrlSecondary/);
  });

  it('el back se revela en hover del media y arranca oculto', () => {
    expect(FBT).toMatch(/card__img--back[^}]*opacity:\s*0/);
    const revealRules = backRules(FBT).filter((rule) => /opacity:\s*1/.test(rule));
    expect(revealRules.some((rule) => rule.includes(':hover'))).toBe(true);
  });
});

describe('ProductRecentlyViewed — hover muestra la imagen trasera (T3.5)', () => {
  it('el grid SSR emite <img data-role="back"> alimentado por imageUrlSecondary', () => {
    const grid = RECENT.slice(RECENT.indexOf('{showServerGrid'), RECENT.indexOf('{useFallback'));
    expect(grid).toMatch(/data-role="back"/);
    expect(RECENT).toMatch(/imageUrlSecondary/);
    // el back se renderiza sólo cuando hay imagen de hover (condicional)
    expect(grid).toMatch(/hoverImageOf|imageUrlSecondary/);
  });

  it('el template del fallback JS también cablea el back', () => {
    const fallback = RECENT.slice(RECENT.indexOf('renderRecentFallback'));
    expect(fallback).toMatch(/data-role="back"/);
    expect(fallback).toMatch(/imageUrlSecondary/);
  });
});
