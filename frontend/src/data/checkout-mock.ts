/**
 * Mock data — checkout flow (F4.1).
 *
 * Este archivo existe SOLO porque el endpoint real
 * `GET/POST /api/storefront/checkout` aún no está implementado. Cuando
 * el endpoint exista, este archivo se reemplaza por fetch + hidratación.
 * La shape de los tipos (CheckoutCartItem, SavedAddress, PaymentMethod,
 * CheckoutStep) es lo único estable que cruza la frontera.
 *
 * Decisiones de diseño (Baymard §5.2):
 *  - Steps con ID semántico (`contact`/`shipping`/`payment`/`review`)
 *    para que el stepper clickeable pueda deep-linkear a cada uno
 *    vía query param `?step=`.
 *  - Saved addresses presentes (3) — el flujo contact step las
 *    muestra si el usuario está "logged in" (placeholder UI state).
 *  - Payment methods cubren B2C + B2B NET 30 placeholder.
 *  - Shipping methods exponen precios variantes para que el summary
 *    lateral actualice el total al elegirlos.
 */
import { PRODUCTS } from './products';

/** Item de la línea de checkout (espejo del CartItem del carrito). */
export interface CheckoutCartItem {
  id: number;
  slug: string;
  name: string;
  imageUrl: string;
  priceCents: number;
  currency: string;
  quantity: number;
}

/** Dirección guardada del usuario (modelo mínimo Baymard §5.2). */
export interface SavedAddress {
  id: string;
  label: string;
  recipient: string;
  phone: string;
  country: string;
  department: string;
  municipality: string;
  address1: string;
  address2?: string;
  reference?: string;
  isDefault?: boolean;
}

/** Método de envío con precio y ETA. */
export interface ShippingMethodOption {
  id: 'standard' | 'express' | 'pickup';
  /** Costo en cents (0 = gratis). */
  priceCents: number;
  /** ETA en días (null para pickup). */
  etaDays: [number, number] | null;
}

/** Método de pago. */
export interface PaymentMethodOption {
  id: 'card' | 'transfer' | 'cod' | 'net30';
  /** Mostrar como opción B2B-only (NET 30). */
  b2bOnly: boolean;
}

/** Identificadores canónicos de cada step (deep-linkable). */
export type CheckoutStepId = 'contact' | 'shipping' | 'payment' | 'review';

export interface CheckoutStepDefinition {
  id: CheckoutStepId;
  index: number;
}

const PLACEHOLDER_IMAGE = PRODUCTS[0].imageUrl;

/**
 * Items demo para que la página renderice visible. Mismas elecciones
 * que `cart.astro` (2 productos con qty distintas) — coherencia entre
 * cart ↔ checkout. Cuando exista el cart store global, este array se
 * reemplaza por `cart.items`.
 */
export const CHECKOUT_CART_ITEMS: readonly CheckoutCartItem[] = [
  {
    id: PRODUCTS[0].id,
    slug: PRODUCTS[0].slug,
    name: PRODUCTS[0].name,
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: PRODUCTS[0].priceCents,
    currency: PRODUCTS[0].currency ?? 'USD',
    quantity: 2,
  },
  {
    id: PRODUCTS[2].id,
    slug: PRODUCTS[2].slug,
    name: PRODUCTS[2].name,
    imageUrl: PRODUCTS[2].imageUrl,
    priceCents: PRODUCTS[2].priceCents,
    currency: PRODUCTS[2].currency ?? 'USD',
    quantity: 1,
  },
];

/**
 * Direcciones guardadas del usuario "logged in" placeholder.
 * Cuando el endpoint exista, el componente consume la lista real.
 */
export const SAVED_ADDRESSES: readonly SavedAddress[] = [
  {
    id: 'addr-home',
    label: 'Casa',
    recipient: 'María Pérez',
    phone: '+505 8888 1234',
    country: 'NI',
    department: 'Managua',
    municipality: 'Managua',
    address1: 'Colonia Centroamérica, calle 123',
    address2: 'Casa #45',
    reference: 'Portón azul, frente al parque',
    isDefault: true,
  },
  {
    id: 'addr-office',
    label: 'Oficina',
    recipient: 'María Pérez',
    phone: '+505 2222 5678',
    country: 'NI',
    department: 'Managua',
    municipality: 'Managua',
    address1: 'Edificio Escala, piso 5, oficina 502',
    reference: 'Torre B, entrada por zona comercial',
  },
  {
    id: 'addr-parents',
    label: 'Casa de mis padres',
    recipient: 'Carlos Pérez',
    phone: '+505 7777 9012',
    country: 'NI',
    department: 'León',
    municipality: 'León',
    address1: 'Barrio San Felipe, calle Real',
    reference: 'Casa esquinera con mural',
  },
];

/** Métodos de envío disponibles con su costo. */
export const SHIPPING_METHODS: readonly ShippingMethodOption[] = [
  {
    id: 'standard',
    priceCents: 400,
    etaDays: [3, 5],
  },
  {
    id: 'express',
    priceCents: 1200,
    etaDays: [1, 2],
  },
  {
    id: 'pickup',
    priceCents: 0,
    etaDays: null,
  },
];

/** Métodos de pago (incluye NET 30 B2B). */
export const PAYMENT_METHODS: readonly PaymentMethodOption[] = [
  { id: 'card', b2bOnly: false },
  { id: 'transfer', b2bOnly: false },
  { id: 'cod', b2bOnly: false },
  { id: 'net30', b2bOnly: true },
];

/**
 * Definición del stepper. El orden del array ES el orden visual
 * (izquierda → derecha).
 */
export const CHECKOUT_STEPS: readonly CheckoutStepDefinition[] = [
  { id: 'contact', index: 0 },
  { id: 'shipping', index: 1 },
  { id: 'payment', index: 2 },
  { id: 'review', index: 3 },
];

/**
 * Helper: subtotal en cents a partir del carrito actual.
 */
export function getCheckoutSubtotalCents(): number {
  return CHECKOUT_CART_ITEMS.reduce((total, item) => total + item.priceCents * item.quantity, 0);
}

/**
 * Helper: costo de envío por id (cents). Devuelve `null` si el id
 * no matchea — útil para "no seleccionado".
 */
export function getShippingMethodPriceCents(id: ShippingMethodOption['id'] | null): number | null {
  if (id === null) return null;
  const method = SHIPPING_METHODS.find((entry) => entry.id === id);
  return method?.priceCents ?? null;
}

/**
 * Helper: placeholder para saber si el usuario está "logged in" (B2C)
 * o no. Hoy siempre es `true` (demo) — cuando el endpoint de sesión
 * exista, este flag se hidrata del backend.
 */
export const IS_USER_LOGGED_IN = true;
