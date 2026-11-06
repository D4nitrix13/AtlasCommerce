/**
 * Tests de la lógica pura de cantidad de la barra de compra del PDP.
 *
 * Casos cubiertos:
 *   - `quantityOptions`: 1..stock con tope duro en MAX_QUANTITY_OPTIONS,
 *     y lista vacía cuando no hay stock.
 *   - `clampToStock`: acota cualquier valor programático al rango
 *     comprable [1..stock]; sin stock no hay cantidad válida (0).
 *   - `quantityStepperState`: semántica del stepper − / + de la barra
 *     de compra (T3.2) — el decremento se detiene en 1, el incremento
 *     en el stock, y los flags de botón marcan los extremos.
 */
import { describe, expect, it } from 'vitest';
import {
  MAX_QUANTITY_OPTIONS,
  clampToStock,
  quantityOptions,
  quantityStepperState,
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

describe('quantityStepperState — pasos', () => {
  it('el decremento se detiene en 1', () => {
    // − baja una unidad mientras haya rango; en el mínimo el
    // resultado sigue siendo 1 y el botón queda deshabilitado.
    expect(quantityStepperState(3, -1, 5)).toEqual({
      value: 2,
      decrementDisabled: false,
      incrementDisabled: false,
    });
    expect(quantityStepperState(1, -1, 5)).toEqual({
      value: 1,
      decrementDisabled: true,
      incrementDisabled: false,
    });
    expect(quantityStepperState(1, -1, 5).value).toBe(1);
  });

  it('el incremento se detiene en el stock', () => {
    // + sube una unidad mientras quede stock; en el tope el
    // resultado sigue siendo el stock y el botón queda deshabilitado.
    expect(quantityStepperState(3, 1, 5)).toEqual({
      value: 4,
      decrementDisabled: false,
      incrementDisabled: false,
    });
    expect(quantityStepperState(5, 1, 5)).toEqual({
      value: 5,
      decrementDisabled: false,
      incrementDisabled: true,
    });
    expect(quantityStepperState(5, 1, 5).value).toBe(5);
  });
});

describe('quantityStepperState — flags de extremos', () => {
  it('sin paso (delta 0) reporta los flags de los extremos', () => {
    expect(quantityStepperState(1, 0, 5)).toEqual({
      value: 1,
      decrementDisabled: true,
      incrementDisabled: false,
    });
    expect(quantityStepperState(5, 0, 5)).toEqual({
      value: 5,
      decrementDisabled: false,
      incrementDisabled: true,
    });
    expect(quantityStepperState(3, 0, 5)).toEqual({
      value: 3,
      decrementDisabled: false,
      incrementDisabled: false,
    });
  });

  it('sin stock el stepper queda inmóvil con ambos botones deshabilitados', () => {
    expect(quantityStepperState(1, -1, 0)).toEqual({
      value: 0,
      decrementDisabled: true,
      incrementDisabled: true,
    });
    expect(quantityStepperState(1, 1, -2)).toEqual({
      value: 0,
      decrementDisabled: true,
      incrementDisabled: true,
    });
  });

  it('acota valores fuera de rango antes de evaluar los extremos', () => {
    // Entradas inválidas (NaN, infinito) caen al mismo clamp que
    // `clampToStock`: ningún valor se cuela fuera de [1..stock].
    expect(quantityStepperState(Number.NaN, 0, 5).value).toBe(1);
    expect(quantityStepperState(Number.POSITIVE_INFINITY, 0, 5)).toEqual({
      value: 5,
      decrementDisabled: false,
      incrementDisabled: true,
    });
  });
});
