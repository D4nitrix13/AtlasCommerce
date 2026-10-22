/**
 * Tests de los helpers puros de paginación del catálogo — extraídos del
 * frontmatter de `pages/[locale]/products/index.astro`.
 *
 * Casos cubiertos:
 *   - `parsePageParam`: null / vacío / no-entero / cero / negativos /
 *     exponencial / hex / fuera del rango seguro → siempre página 1.
 *   - `parsePageParam`: enteros válidos (incluido con padding de ceros).
 *   - `clampPage`: dentro de rango, por debajo de 1, por encima de
 *     `last_page`, y `last_page` inservible (0, negativo, NaN, Infinity).
 *
 * El contrato que sostienen juntos: `clampPage(parsePageParam(x), n)` nunca
 * devuelve un número fuera de `[1, max(1, n)]`.
 */
import { describe, expect, it } from 'vitest';
import { clampPage, FIRST_PAGE, parsePageParam } from '../../../src/lib/pagination';

describe('parsePageParam', () => {
  it('devuelve página 1 cuando no hay query param', () => {
    expect(parsePageParam(null)).toBe(FIRST_PAGE);
  });

  it('devuelve página 1 para valores vacíos o sólo espacios', () => {
    expect(parsePageParam('')).toBe(FIRST_PAGE);
    expect(parsePageParam('   ')).toBe(FIRST_PAGE);
  });

  it('parsea enteros positivos', () => {
    expect(parsePageParam('1')).toBe(1);
    expect(parsePageParam('2')).toBe(2);
    expect(parsePageParam('9999')).toBe(9999);
  });

  it('tolera padding de ceros y espacios alrededor', () => {
    expect(parsePageParam('007')).toBe(7);
    expect(parsePageParam('  12  ')).toBe(12);
  });

  it('rechaza no-enteros que Number() sí parsearía', () => {
    // `Number()` convierte estas strings a 1000 / 16 / 1.5 — ninguna es
    // una página válida en la URL.
    expect(parsePageParam('1e3')).toBe(FIRST_PAGE);
    expect(parsePageParam('0x10')).toBe(FIRST_PAGE);
    expect(parsePageParam('1.5')).toBe(FIRST_PAGE);
    expect(parsePageParam('abc')).toBe(FIRST_PAGE);
    expect(parsePageParam('NaN')).toBe(FIRST_PAGE);
    expect(parsePageParam('Infinity')).toBe(FIRST_PAGE);
    expect(parsePageParam('2;drop')).toBe(FIRST_PAGE);
  });

  it('rechaza cero y negativos (la paginación es 1-indexed)', () => {
    expect(parsePageParam('0')).toBe(FIRST_PAGE);
    expect(parsePageParam('-3')).toBe(FIRST_PAGE);
  });

  it('rechaza números fuera del rango seguro de JS', () => {
    expect(parsePageParam('99999999999999999999')).toBe(FIRST_PAGE);
  });
});

describe('clampPage', () => {
  it('deja intacta una página dentro del rango', () => {
    expect(clampPage(1, 5)).toBe(1);
    expect(clampPage(3, 5)).toBe(3);
    expect(clampPage(5, 5)).toBe(5);
  });

  it('baja a la última página cuando la pedida la excede (?page=9999)', () => {
    expect(clampPage(9999, 15)).toBe(15);
  });

  it('sube a página 1 cuando la pedida es menor que 1', () => {
    expect(clampPage(0, 5)).toBe(FIRST_PAGE);
    expect(clampPage(-4, 5)).toBe(FIRST_PAGE);
  });

  it('trata un lastPage inservible como catálogo de una sola página', () => {
    expect(clampPage(7, 0)).toBe(FIRST_PAGE);
    expect(clampPage(7, -3)).toBe(FIRST_PAGE);
    expect(clampPage(7, Number.NaN)).toBe(FIRST_PAGE);
    expect(clampPage(7, Number.POSITIVE_INFINITY)).toBe(FIRST_PAGE);
  });

  it('recorta un lastPage fraccionario', () => {
    expect(clampPage(9, 4.7)).toBe(4);
  });
});

describe('parsePageParam + clampPage (contrato combinado)', () => {
  it('nunca devuelve una página fuera de [1, lastPage]', () => {
    const inputs = [null, '', 'abc', '0', '-2', '1', '3', '9999', '1e3'];

    for (const input of inputs) {
      for (const lastPage of [0, 1, 4, 15]) {
        const resolved = clampPage(parsePageParam(input), lastPage);
        expect(resolved).toBeGreaterThanOrEqual(FIRST_PAGE);
        expect(resolved).toBeLessThanOrEqual(Math.max(FIRST_PAGE, lastPage));
        expect(Number.isInteger(resolved)).toBe(true);
      }
    }
  });
});