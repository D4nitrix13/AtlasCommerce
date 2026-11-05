/**
 * Tests de la lógica pura de cantidad de la barra de compra del PDP.
 *
 * Casos cubiertos:
 *   - `quantityOptions`: 1..stock con tope duro en MAX_QUANTITY_OPTIONS,
 *     y lista vacía cuando no hay stock.
 *   - `clampToStock`: acota cualquier valor programático al rango
 *     comprable [1..stock]; sin stock no hay cantidad válida (0).
 */
import { describe, expect, it } from 'vitest';
import {
  MAX_QUANTITY_OPTIONS,
  clampToStock,
  quantityOptions,
} from '../../../src/lib/purchase-quantity';

describe('quantityOptions', () => {
  it('devuelve 1..stock cuando el stock es menor al tope', () => {
    expect(quantityOptions(3)).toEqual([1, 2, 3]);
    expect(quantityOptions(1)).toEqual([1]);
  });

  it('acota la lista a MAX_QUANTITY_OPTIONS cuando el stock supera el tope', () => {
    expect(quantityOptions(40)).toHaveLength(MAX_QUANTITY_OPTIONS);
    expect(quantityOptions(40)).toEqual(
      Array.from({ length: MAX_QUANTITY_OPTIONS }, (_, index) => index + 1),
    );
  });

  it('devuelve vacío cuando no hay stock', () => {
    expect(quantityOptions(0)).toEqual([]);
    expect(quantityOptions(-2)).toEqual([]);
  });
});

describe('clampToStock', () => {
  it('respeta los valores dentro del rango', () => {
    expect(clampToStock(1, 5)).toBe(1);
    expect(clampToStock(3, 5)).toBe(3);
    expect(clampToStock(5, 5)).toBe(5);
  });

  it('acota al stock cuando el valor lo excede', () => {
    expect(clampToStock(6, 5)).toBe(5);
    expect(clampToStock(99, 2)).toBe(2);
  });

  it('acota al mínimo 1 cuando el valor es menor', () => {
    expect(clampToStock(0, 5)).toBe(1);
    expect(clampToStock(-3, 5)).toBe(1);
  });

  it('devuelve 0 cuando no hay stock disponible', () => {
    expect(clampToStock(3, 0)).toBe(0);
    expect(clampToStock(3, -1)).toBe(0);
  });

  it('contiene los valores no finitos dentro del rango', () => {
    expect(clampToStock(Number.NaN, 5)).toBe(1);
    expect(clampToStock(Number.POSITIVE_INFINITY, 5)).toBe(5);
    expect(clampToStock(Number.POSITIVE_INFINITY, 0)).toBe(0);
  });
});
