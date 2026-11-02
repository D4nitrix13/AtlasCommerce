/**
 * Store del carrito de compras (React 19 + useSyncExternalStore).
 *
 * Mismo patrón que `stores/auth.ts`: sin dependencias externas, estado
 * en memoria hidratado desde `localStorage` al cargar el módulo, y
 * persistido en cada mutación. Es el carrito del prototipo: vive en el
 * browser, sin backend todavía (TODO F1.4 de cart.astro).
 *
 * Las líneas identifican su pieza por `productSlug` + `variantLabel`
 * (el acabado), porque Panda vende la misma pieza en varios colores y
 * cada variante es una línea propia del carrito.
 *
 * Uso:
 *   const lines = useCart();
 *   addToCart({ productSlug, name, imageUrl, priceCents, currency, variantLabel }, 2);
 */
import { useSyncExternalStore } from 'react';

export interface CartLine {
  productSlug: string;
  name: string;
  imageUrl: string;
  priceCents: number;
  currency: string;
  quantity: number;
  /** Acabado elegido (p. ej. "Negro"). Distingue líneas de la misma pieza. */
  variantLabel?: string;
}

export interface CartState {
  lines: CartLine[];
}

export interface CartTotals {
  subtotalCents: number;
  /** null cuando el carrito está vacío: no hay nada que enviar. */
  shippingCents: number | null;
  taxCents: number;
  totalCents: number;
  itemCount: number;
}

/** Envío gratis sobre $50, sino $4 fijo (placeholder UX, igual que cart.astro). */
export const FREE_SHIPPING_THRESHOLD_CENTS = 5000;
export const FLAT_SHIPPING_CENTS = 400;
/** Impuesto placeholder del 15% sobre el subtotal. */
export const TAX_RATE = 0.15;

const STORAGE_KEY = 'cart.lines';

const EMPTY_STATE: CartState = { lines: [] };

function readLinesFromStorage(): CartLine[] {
  if (typeof window === 'undefined' || !window.localStorage) {
    return [];
  }
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (raw === null) {
      return [];
    }
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.filter(isCartLine);
  } catch {
    return [];
  }
}

function isCartLine(value: unknown): value is CartLine {
  if (typeof value !== 'object' || value === null) {
    return false;
  }
  const line = value as Partial<CartLine>;
  return (
    typeof line.productSlug === 'string' &&
    typeof line.name === 'string' &&
    typeof line.imageUrl === 'string' &&
    typeof line.priceCents === 'number' &&
    typeof line.currency === 'string' &&
    typeof line.quantity === 'number'
  );
}

function persistLines(lines: CartLine[]): void {
  if (typeof window === 'undefined' || !window.localStorage) {
    return;
  }
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
}

let state: CartState = { lines: readLinesFromStorage() };

function setState(next: CartLine[]): void {
  state = { lines: next };
  persistLines(next);
  for (const listener of listeners) {
    listener();
  }
}

const listeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

function getSnapshot(): CartState {
  return state;
}

function isSameLine(line: CartLine, productSlug: string, variantLabel?: string): boolean {
  return line.productSlug === productSlug && line.variantLabel === variantLabel;
}

export function addToCart(
  line: Omit<CartLine, 'quantity'>,
  quantity = 1,
): void {
  const existing = state.lines.find((l) =>
    isSameLine(l, line.productSlug, line.variantLabel),
  );
  if (existing !== undefined) {
    setState(
      state.lines.map((l) =>
        l === existing ? { ...l, quantity: l.quantity + quantity } : l,
      ),
    );
    return;
  }
  setState([...state.lines, { ...line, quantity }]);
}

export function updateQuantity(
  productSlug: string,
  quantity: number,
  variantLabel?: string,
): void {
  if (quantity <= 0) {
    removeFromCart(productSlug, variantLabel);
    return;
  }
  setState(
    state.lines.map((l) =>
      isSameLine(l, productSlug, variantLabel) ? { ...l, quantity } : l,
    ),
  );
}

export function removeFromCart(productSlug: string, variantLabel?: string): void {
  setState(
    state.lines.filter((l) => !isSameLine(l, productSlug, variantLabel)),
  );
}

export function clearCart(): void {
  setState([]);
}

export function getCartTotals(lines: CartLine[]): CartTotals {
  const subtotalCents = lines.reduce(
    (total, line) => total + line.priceCents * line.quantity,
    0,
  );
  const itemCount = lines.reduce((total, line) => total + line.quantity, 0);
  const shippingCents =
    lines.length === 0
      ? null
      : subtotalCents >= FREE_SHIPPING_THRESHOLD_CENTS
        ? 0
        : FLAT_SHIPPING_CENTS;
  const taxCents = Math.round(subtotalCents * TAX_RATE);
  return {
    subtotalCents,
    shippingCents,
    taxCents,
    totalCents: subtotalCents + (shippingCents ?? 0) + taxCents,
    itemCount,
  };
}

export const cartStore = {
  addToCart,
  updateQuantity,
  removeFromCart,
  clearCart,
  subscribe,
  getSnapshot,
};

export function useCart(): CartState {
  return useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
}
