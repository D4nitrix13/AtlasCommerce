/**
 * Tests de la lógica de navegación por teclado del tab strip del PDP.
 *
 * Contrato (WAI-ARIA APG tabs, activación automática):
 *   - ArrowRight / ArrowLeft: mueven el foco (y activan) al tab
 *     siguiente / anterior, con wrap-around en los extremos.
 *   - Home / End: saltan al primer / último tab.
 *   - Teclas no navegables: `nextTabIndex` devuelve el índice actual
 *     y el caller no intercepta el evento (Enter/Space activan vía
 *     click nativo del `<button>`).
 */
import { describe, expect, it } from 'vitest';
import { isTabNavKey, nextTabIndex } from '../../../src/lib/pdp-tabs';

describe('nextTabIndex', () => {
  it('ArrowRight avanza al siguiente tab', () => {
    expect(nextTabIndex(0, 5, 'ArrowRight')).toBe(1);
    expect(nextTabIndex(2, 5, 'ArrowRight')).toBe(3);
  });

  it('ArrowRight hace wrap del último al primero', () => {
    expect(nextTabIndex(4, 5, 'ArrowRight')).toBe(0);
  });

  it('ArrowLeft retrocede al tab anterior', () => {
    expect(nextTabIndex(3, 5, 'ArrowLeft')).toBe(2);
    expect(nextTabIndex(1, 5, 'ArrowLeft')).toBe(0);
  });

  it('ArrowLeft hace wrap del primero al último', () => {
    expect(nextTabIndex(0, 5, 'ArrowLeft')).toBe(4);
  });

  it('Home salta al primer tab', () => {
    expect(nextTabIndex(3, 5, 'Home')).toBe(0);
  });

  it('End salta al último tab', () => {
    expect(nextTabIndex(1, 5, 'End')).toBe(4);
  });

  it('con un solo tab toda tecla navegable devuelve 0', () => {
    for (const key of ['ArrowRight', 'ArrowLeft', 'Home', 'End']) {
      expect(nextTabIndex(0, 1, key)).toBe(0);
    }
  });

  it('teclas no navegables devuelven el índice actual', () => {
    expect(nextTabIndex(2, 5, 'Enter')).toBe(2);
    expect(nextTabIndex(2, 5, ' ')).toBe(2);
    expect(nextTabIndex(2, 5, 'a')).toBe(2);
    expect(nextTabIndex(2, 5, 'Tab')).toBe(2);
  });
});

describe('isTabNavKey', () => {
  it('acepta las 4 teclas navegables', () => {
    for (const key of ['ArrowRight', 'ArrowLeft', 'Home', 'End']) {
      expect(isTabNavKey(key)).toBe(true);
    }
  });

  it('rechaza el resto de las teclas', () => {
    for (const key of ['Enter', ' ', 'a', 'Tab', 'arrowright', 'Escape']) {
      expect(isTabNavKey(key)).toBe(false);
    }
  });
});
