/** @vitest-environment jsdom */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
  addToCart,
  cartStore,
  clearCart,
  getCartTotals,
  removeFromCart,
  updateQuantity,
} from '../../../src/stores/cart';

const STORAGE_KEY = 'cart.lines';

beforeEach(() => {
  // Guard defensivo: en este archivo jsdom a veces no expone
  // window.localStorage en el momento de beforeEach.
  if (typeof window !== 'undefined' && window.localStorage) {
    window.localStorage.clear();
  }
  clearCart();
});

function snapshotLines() {
  return cartStore.getSnapshot().lines;
}

/**
 * El store hidrata desde localStorage al cargar el módulo. Para
 * observar esa hidratación hay que re-importarlo con el storage ya
 * poblado (vi.resetModules + import dinámico).
 */
async function importFreshCart() {
  vi.resetModules();
  return import('../../../src/stores/cart');
}

const polo = {
  productSlug: 'polo-pique',
  name: 'Polo piqué',
  imageUrl: 'polo.svg',
  priceCents: 2400,
  currency: 'USD',
  variantLabel: 'Negro',
};

const taza = {
  productSlug: 'taza-11-oz',
  name: 'Taza 11 oz',
  imageUrl: 'taza.svg',
  priceCents: 1200,
  currency: 'USD',
  variantLabel: 'Blanco',
};

describe('stores/cart', () => {
  describe('estado inicial', () => {
    it('arranca vacío cuando no hay nada persistido', () => {
      expect(snapshotLines()).toEqual([]);
    });

    it('recupera las líneas persistidas en localStorage', async () => {
      window.localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify([{ ...polo, quantity: 2 }]),
      );
      const fresh = await importFreshCart();
      expect(fresh.cartStore.getSnapshot().lines).toEqual([
        { ...polo, quantity: 2 },
      ]);
    });

    it('cae a vacío cuando el localStorage está corrupto', async () => {
      window.localStorage.setItem(STORAGE_KEY, '{no-es-json');
      const fresh = await importFreshCart();
      expect(fresh.cartStore.getSnapshot().lines).toEqual([]);
    });
  });

  describe('addToCart', () => {
    it('agrega una línea nueva', () => {
      addToCart(polo, 1);
      expect(snapshotLines()).toEqual([{ ...polo, quantity: 1 }]);
    });

    it('acumula cantidad cuando es la misma pieza y el mismo acabado', () => {
      addToCart(polo, 1);
      addToCart(polo, 3);
      expect(snapshotLines()).toEqual([{ ...polo, quantity: 4 }]);
    });

    it('mantiene líneas separadas por acabado distinto', () => {
      addToCart(polo, 1);
      addToCart({ ...polo, variantLabel: 'Crema' }, 2);
      expect(snapshotLines()).toHaveLength(2);
    });

    it('persiste en localStorage', () => {
      addToCart(taza, 2);
      const raw = window.localStorage.getItem(STORAGE_KEY);
      expect(raw).not.toBeNull();
      expect(JSON.parse(raw ?? '[]')).toEqual([{ ...taza, quantity: 2 }]);
    });
  });

  describe('updateQuantity', () => {
    it('actualiza la cantidad de una línea', () => {
      addToCart(polo, 1);
      updateQuantity('polo-pique', 5, 'Negro');
      expect(snapshotLines()[0]?.quantity).toBe(5);
    });

    it('elimina la línea cuando la cantidad llega a 0', () => {
      addToCart(polo, 1);
      updateQuantity('polo-pique', 0, 'Negro');
      expect(snapshotLines()).toEqual([]);
    });

    it('no toca otras líneas', () => {
      addToCart(polo, 1);
      addToCart(taza, 1);
      updateQuantity('polo-pique', 9, 'Negro');
      expect(snapshotLines().find((l) => l.productSlug === 'taza-11-oz')?.quantity).toBe(1);
    });
  });

  describe('removeFromCart', () => {
    it('elimina la línea indicada', () => {
      addToCart(polo, 1);
      addToCart(taza, 1);
      removeFromCart('polo-pique', 'Negro');
      expect(snapshotLines()).toEqual([{ ...taza, quantity: 1 }]);
    });
  });

  describe('clearCart', () => {
    it('vacía el carrito y el storage', () => {
      addToCart(polo, 2);
      clearCart();
      expect(snapshotLines()).toEqual([]);
      expect(window.localStorage.getItem(STORAGE_KEY)).toBe('[]');
    });
  });

  describe('getCartTotals', () => {
    it('calcula el subtotal de las líneas', () => {
      const totals = getCartTotals([
        { ...polo, quantity: 2 },
        { ...taza, quantity: 1 },
      ]);
      expect(totals.subtotalCents).toBe(2400 * 2 + 1200);
    });

    it('cobra envío fijo de $4 por debajo del umbral de $50', () => {
      const totals = getCartTotals([{ ...taza, quantity: 1 }]);
      expect(totals.shippingCents).toBe(400);
    });

    it('da envío gratis sobre $50', () => {
      const totals = getCartTotals([{ ...polo, quantity: 3 }]);
      expect(totals.subtotalCents).toBe(7200);
      expect(totals.shippingCents).toBe(0);
    });

    it('aplica el impuesto placeholder del 15% sobre el subtotal', () => {
      const totals = getCartTotals([{ ...taza, quantity: 1 }]);
      expect(totals.taxCents).toBe(180);
    });

    it('el total es subtotal + envío + impuesto', () => {
      const totals = getCartTotals([{ ...taza, quantity: 1 }]);
      expect(totals.totalCents).toBe(1200 + 400 + 180);
    });

    it('sin líneas el envío es null y todo en 0', () => {
      const totals = getCartTotals([]);
      expect(totals).toEqual({
        subtotalCents: 0,
        shippingCents: null,
        taxCents: 0,
        totalCents: 0,
        itemCount: 0,
      });
    });
  });
});
