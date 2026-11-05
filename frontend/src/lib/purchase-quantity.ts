/**
 * Lógica pura de cantidad para la barra de compra del PDP.
 *
 * Compartida por la isla `ProductPurchaseBar`: el clamp vive acá y no
 * en el componente para que los límites del stock sean testeables sin
 * montar React, y para que cualquier otra isla de compra reutilice la
 * misma regla.
 */

/** Tope de opciones del selector: stock mayor muestra 1..25. */
export const MAX_QUANTITY_OPTIONS = 25;

/**
 * Opciones del selector de cantidad para un stock dado.
 *
 * Devuelve `1..min(stock, MAX_QUANTITY_OPTIONS)` y vacío cuando no hay
 * stock: sin unidades no hay cantidad seleccionable.
 */
export function quantityOptions(stock: number): number[] {
  if (stock <= 0) {
    return [];
  }
  const top = Math.min(stock, MAX_QUANTITY_OPTIONS);
  return Array.from({ length: top }, (_, index) => index + 1);
}

/**
 * Acota un valor de cantidad al rango comprable `[1..stock]`.
 *
 * Sin stock no hay cantidad válida y devuelve 0. El NaN (parseo
 * fallido) cae al mínimo 1, y el infinito se acota al stock: ningún
 * valor puede colarse por encima del stock, que es el límite que
 * protege esta función.
 */
export function clampToStock(value: number, stock: number): number {
  if (stock <= 0) {
    return 0;
  }
  if (Number.isNaN(value)) {
    return 1;
  }
  return Math.min(stock, Math.max(1, value));
}

/** Estado del stepper − / + de la barra de compra. */
export interface QuantityStepperState {
  /** Valor acotado al rango comprable. */
  value: number;
  /** `true` cuando − ya no puede bajar (mínimo 1). */
  decrementDisabled: boolean;
  /** `true` cuando + ya no puede subir (tope = stock). */
  incrementDisabled: boolean;
}

/**
 * Estado del stepper de cantidad tras aplicar `delta` al valor actual.
 *
 * `delta` 0 sólo lee el estado (para los flags de los botones). El
 * resultado se acota al rango comprable `[1..stock]` (0 sin stock):
 * el decremento se detiene en 1 y el incremento en el stock. Los
 * flags marcan los extremos para deshabilitar − / + donde ya no hay
 * movimiento posible.
 */
export function quantityStepperState(
  current: number,
  delta: number,
  stock: number,
): QuantityStepperState {
  const value = clampToStock(current + delta, stock);
  return {
    value,
    decrementDisabled: value <= 1,
    incrementDisabled: stock <= 0 || value >= stock,
  };
}
