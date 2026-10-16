/**
 * Mock data — órdenes completas del cliente para `/account/orders` (F2.1)
 * y `/account/orders/[id]` (F2.2).
 *
 * Este archivo existe SOLO porque `GET /api/storefront/orders` aún no
 * está implementado. Cuando el endpoint exista, se reemplaza por fetch
 * + hidratación React. La shape de `OrderMock` es lo único estable
 * que cruza la frontera con el backend real.
 *
 * Decisiones:
 *  - `totalCents`: cents (entero) como manda §10 AGENTS — el formateo
 *    se hace en UI con `formatCurrency()`.
 *  - `status`: matchea exactamente las variants del `Badge` primitive
 *    (design-refs §6.4).
 *  - `milestones`: 5 hitos para OrderTimeline §6.3. Los timestamps
 *    son opcionales para que la timeline soporte status parciales
 *    (por ejemplo `pending` con sólo el primer milestone seteado).
 *  - `trackingNumber`: presente sólo cuando `status >= 'shipped'`.
 *  - 18 órdenes variadas cubren todos los estados y dan densidad
 *    suficiente para probar paginación/filtrado.
 */

import type { OrderStatus } from './account-mock';

export type OrderMilestoneKey =
  'received' | 'preparing' | 'shipped' | 'outForDelivery' | 'delivered';

export interface OrderMilestoneMock {
  key: OrderMilestoneKey;
  /** ISO 8601. `null` = aún no ocurrido. */
  timestamp: string | null;
  note?: string;
}

export interface OrderItemMock {
  id: number;
  productSlug: string;
  productName: string;
  imageUrl: string;
  quantity: number;
  unitPriceCents: number;
}

export interface OrderShippingAddressMock {
  recipient: string;
  phone: string;
  country: string;
  department: string;
  municipality: string;
  detailedAddress: string;
  reference?: string;
}

export interface OrderPaymentMock {
  method: 'card' | 'transfer' | 'cash_on_delivery';
  last4?: string;
  brand?: string;
}

export interface OrderMock {
  id: string;
  date: string; // ISO 8601
  status: OrderStatus;
  totalCents: number;
  items: OrderItemMock[];
  shippingAddress: OrderShippingAddressMock;
  payment: OrderPaymentMock;
  trackingNumber?: string;
  trackingCarrier?: string;
  trackingUrl?: string;
  milestones: OrderMilestoneMock[];
}

const PLACEHOLDER_IMAGE =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="Order item placeholder">
      <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#f1f5f9"/>
          <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
      </defs>
      <rect width="400" height="400" fill="url(#g)"/>
      <circle cx="200" cy="160" r="60" fill="#cbd5e1"/>
      <path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/>
      <text x="200" y="370" text-anchor="middle" font-family="system-ui, sans-serif" font-size="16" fill="#64748b">Order item</text>
    </svg>`
  );

const DEFAULT_SHIPPING: OrderShippingAddressMock = {
  recipient: 'Daniel Benjamin Pérez Morales',
  phone: '+505 8888 1234',
  country: 'Nicaragua',
  department: 'Managua',
  municipality: 'Managua',
  detailedAddress: 'Residencias Bolonia, Calle las Rosas #14',
  reference: 'Casa esquinera, portón negro',
};

const DEFAULT_PAYMENT: OrderPaymentMock = {
  method: 'card',
  brand: 'Visa',
  last4: '4242',
};

/**
 * Genera milestones coherentes con el estado actual del pedido.
 *
 * Reglas:
 *  - `pending`    → sólo `received` con timestamp.
 *  - `processing` → `received` + `preparing`.
 *  - `shipped`    → `received` + `preparing` + `shipped`.
 *  - `delivered`  → los 5 milestones con timestamp.
 *  - `cancelled`  → solo `received`, sin progresión.
 *
 * Los timestamps se calculan hacia atrás desde `date` para mantener
 * estabilidad entre renders (datos determinísticos).
 */
function buildMilestones(status: OrderStatus, date: string): OrderMilestoneMock[] {
  const orderTime = new Date(`${date}T12:00:00Z`).getTime();
  const HOUR = 60 * 60 * 1000;

  // `at` es un helper local que produce un ISO 8601 desplazado desde
  // la fecha del pedido. Mantiene el cálculo de offsets legible.
  const at = (hoursBack: number): string => new Date(orderTime - hoursBack * HOUR).toISOString();

  const milestones: OrderMilestoneMock[] = [
    { key: 'received', timestamp: at(72) },
    { key: 'preparing', timestamp: null },
    { key: 'shipped', timestamp: null },
    { key: 'outForDelivery', timestamp: null },
    { key: 'delivered', timestamp: null },
  ];

  applyStatusToMilestones(milestones, status, at);
  return milestones;
}

/**
 * Aplica el status del pedido al array de milestones, seteando
 * timestamps en los hitos alcanzados. Mantiene `buildMilestones`
 * dentro del límite de 40 líneas de AGENTS §1.
 *
 * Cada status tiene su propia función de mutación. El mapa
 * STATUS_MUTATORS centraliza el switch y deja la lógica plana.
 */
function applyStatusToMilestones(
  milestones: OrderMilestoneMock[],
  status: OrderStatus,
  at: (hoursBack: number) => string
): void {
  const [received, preparing, shipped, outForDelivery, delivered] = milestones;
  if (
    received === undefined ||
    preparing === undefined ||
    shipped === undefined ||
    outForDelivery === undefined ||
    delivered === undefined
  ) {
    throw new Error('orders-mock: milestones array must have 5 entries');
  }

  STATUS_MUTATORS[status]({
    received,
    preparing,
    shipped,
    outForDelivery,
    delivered,
    at,
  });
}

interface MilestoneMutatorDeps {
  received: OrderMilestoneMock;
  preparing: OrderMilestoneMock;
  shipped: OrderMilestoneMock;
  outForDelivery: OrderMilestoneMock;
  delivered: OrderMilestoneMock;
  at: (hoursBack: number) => string;
}

const STATUS_MUTATORS: Record<OrderStatus, (d: MilestoneMutatorDeps) => void> = {
  pending: () => {
    // Solo received.
  },
  processing: ({ preparing, at }) => {
    preparing.timestamp = at(24);
  },
  shipped: ({ preparing, shipped, at }) => {
    preparing.timestamp = at(48);
    shipped.timestamp = at(12);
    shipped.note = 'Courier in transit to local hub';
  },
  delivered: ({ preparing, shipped, outForDelivery, delivered, at }) => {
    preparing.timestamp = at(96);
    shipped.timestamp = at(72);
    outForDelivery.timestamp = at(6);
    delivered.timestamp = at(0);
  },
  cancelled: ({ received }) => {
    received.note = 'Order cancelled before processing';
  },
};

/**
 * Catálogo reducido de items mock. Reutilizable para OrdersTable y
 * WishlistItem. No es data de verdad: los slugs y nombres son del
 * `data/products.ts` existente.
 */
const ORDER_ITEM_CATALOG: readonly Omit<OrderItemMock, 'quantity'>[] = [
  {
    id: 1,
    productSlug: 'saber-alter-figma',
    productName: 'Saber Alter Figma',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 14500,
  },
  {
    id: 2,
    productSlug: 'tanjiro-kimono-no-youjin',
    productName: 'Tanjiro Kamado — Kimono no Youjin',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 3900,
  },
  {
    id: 3,
    productSlug: 'sakura-card-captor-figura',
    productName: 'Sakura Card Captor — Nendoroid',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 7900,
  },
  {
    id: 4,
    productSlug: 'totoro-plush-medium',
    productName: 'Totoro Plush (Medium)',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 4500,
  },
  {
    id: 5,
    productSlug: 'eva-unit-01-manga-tome-1',
    productName: 'Evangelion Unit 01 — Manga Tome 1',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 2800,
  },
  {
    id: 6,
    productSlug: 'jujutsu-kaisen-tenen-shirt',
    productName: 'Jujutsu Kaisen — Tenen Shirt',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 3200,
  },
  {
    id: 7,
    productSlug: 'frieren-wallet',
    productName: 'Frieren — Leather Wallet',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 5400,
  },
  {
    id: 8,
    productSlug: 'hatsune-miku-figura',
    productName: 'Hatsune Miku — Vocaloid Figure',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 16900,
  },
  {
    id: 9,
    productSlug: 'rem-ram-strap-set',
    productName: 'Rem & Ram Strap Set',
    imageUrl: PLACEHOLDER_IMAGE,
    unitPriceCents: 2200,
  },
];

function pickItems(
  indices: readonly { catalogIndex: number; quantity: number }[]
): OrderItemMock[] {
  return indices.map(({ catalogIndex, quantity }, idx) => {
    const catalogItem = ORDER_ITEM_CATALOG[catalogIndex];
    if (catalogItem === undefined) {
      throw new Error(`orders-mock: índice de catálogo fuera de rango ${catalogIndex}`);
    }
    // Clonar con id único por item de orden (compuesto por idx del row).
    return { ...catalogItem, id: (idx + 1) * 100, quantity };
  });
}

/**
 * Conjunto completo de órdenes del cliente (mock).
 *
 * IDs estables (no aleatorios), fechas determinísticas (septiembre →
 * abril 2026 hacia atrás). Cobertura de statuses:
 *   - 4 delivered
 *   - 3 shipped
 *   - 3 processing
 *   - 4 pending
 *   - 2 cancelled
 *
 * Sumando 16. Incluye variaciones de `items.length` (1 a 4) y totales
 * entre $3,200 y $19,800 para validar el formateo.
 */
export const ORDERS_MOCK: readonly OrderMock[] = [
  // ─── Entregados ────────────────────────────────────────────────
  {
    id: '12350',
    date: '2026-09-25',
    status: 'shipped',
    totalCents: 4500,
    items: pickItems([{ catalogIndex: 3, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    trackingNumber: 'NI7891234567',
    trackingCarrier: 'FedEx',
    trackingUrl: 'https://www.fedex.com/fedextrack/?trknum=NI7891234567',
    milestones: buildMilestones('shipped', '2026-09-25'),
  },
  {
    id: '12345',
    date: '2026-09-20',
    status: 'shipped',
    totalCents: 23400,
    items: pickItems([
      { catalogIndex: 0, quantity: 1 },
      { catalogIndex: 6, quantity: 1 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    trackingNumber: 'NI1234567890',
    trackingCarrier: 'DHL',
    trackingUrl: 'https://www.dhl.com/ni-es/home/tracking.html',
    milestones: buildMilestones('shipped', '2026-09-20'),
  },
  {
    id: '12340',
    date: '2026-09-15',
    status: 'delivered',
    totalCents: 12000,
    items: pickItems([
      { catalogIndex: 2, quantity: 1 },
      { catalogIndex: 4, quantity: 1 },
      { catalogIndex: 8, quantity: 1 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    trackingNumber: 'NI0987654321',
    trackingCarrier: 'FedEx',
    milestones: buildMilestones('delivered', '2026-09-15'),
  },
  {
    id: '12338',
    date: '2026-09-10',
    status: 'delivered',
    totalCents: 3400,
    items: pickItems([{ catalogIndex: 4, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    trackingNumber: 'NI4567890123',
    trackingCarrier: 'UPS',
    milestones: buildMilestones('delivered', '2026-09-10'),
  },
  {
    id: '12335',
    date: '2026-09-05',
    status: 'cancelled',
    totalCents: 0,
    items: pickItems([{ catalogIndex: 8, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('cancelled', '2026-09-05'),
  },
  {
    id: '12330',
    date: '2026-08-30',
    status: 'delivered',
    totalCents: 8600,
    items: pickItems([
      { catalogIndex: 6, quantity: 1 },
      { catalogIndex: 9, quantity: 1 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    trackingNumber: 'NI5678901234',
    trackingCarrier: 'DHL',
    milestones: buildMilestones('delivered', '2026-08-30'),
  },
  {
    id: '12325',
    date: '2026-08-22',
    status: 'shipped',
    totalCents: 16900,
    items: pickItems([{ catalogIndex: 7, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: { method: 'cash_on_delivery' },
    trackingNumber: 'NI6789012345',
    trackingCarrier: 'UPS',
    milestones: buildMilestones('shipped', '2026-08-22'),
  },
  // ─── Processing / Pending ──────────────────────────────────────
  {
    id: '12320',
    date: '2026-08-18',
    status: 'processing',
    totalCents: 19800,
    items: pickItems([
      { catalogIndex: 0, quantity: 1 },
      { catalogIndex: 7, quantity: 1 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('processing', '2026-08-18'),
  },
  {
    id: '12315',
    date: '2026-08-12',
    status: 'processing',
    totalCents: 5400,
    items: pickItems([{ catalogIndex: 6, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('processing', '2026-08-12'),
  },
  {
    id: '12310',
    date: '2026-08-05',
    status: 'processing',
    totalCents: 8100,
    items: pickItems([
      { catalogIndex: 2, quantity: 1 },
      { catalogIndex: 5, quantity: 1 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('processing', '2026-08-05'),
  },
  {
    id: '12305',
    date: '2026-07-28',
    status: 'pending',
    totalCents: 4500,
    items: pickItems([{ catalogIndex: 3, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('pending', '2026-07-28'),
  },
  {
    id: '12300',
    date: '2026-07-20',
    status: 'pending',
    totalCents: 14000,
    items: pickItems([
      { catalogIndex: 1, quantity: 2 },
      { catalogIndex: 5, quantity: 1 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: { method: 'transfer' },
    milestones: buildMilestones('pending', '2026-07-20'),
  },
  {
    id: '12295',
    date: '2026-07-10',
    status: 'pending',
    totalCents: 3200,
    items: pickItems([{ catalogIndex: 5, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('pending', '2026-07-10'),
  },
  {
    id: '12290',
    date: '2026-06-30',
    status: 'pending',
    totalCents: 16900,
    items: pickItems([{ catalogIndex: 7, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('pending', '2026-06-30'),
  },
  // ─── Más históricos (delivered/cancelled) ──────────────────────
  {
    id: '12280',
    date: '2026-06-20',
    status: 'delivered',
    totalCents: 10900,
    items: pickItems([
      { catalogIndex: 0, quantity: 1 },
      { catalogIndex: 1, quantity: 2 },
    ]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    trackingNumber: 'NI7890123456',
    trackingCarrier: 'FedEx',
    milestones: buildMilestones('delivered', '2026-06-20'),
  },
  {
    id: '12270',
    date: '2026-05-15',
    status: 'cancelled',
    totalCents: 0,
    items: pickItems([{ catalogIndex: 5, quantity: 1 }]),
    shippingAddress: DEFAULT_SHIPPING,
    payment: DEFAULT_PAYMENT,
    milestones: buildMilestones('cancelled', '2026-05-15'),
  },
];

/** Devuelve todas las órdenes (mock). */
export function getAllOrders(): readonly OrderMock[] {
  return ORDERS_MOCK;
}

/** Devuelve una orden por ID, o `null` si no existe. */
export function findOrderById(id: string): OrderMock | null {
  return ORDERS_MOCK.find((order) => order.id === id) ?? null;
}

/** Cuenta cuántos items hay en una orden (suma de `quantity`). */
export function countOrderItems(order: OrderMock): number {
  return order.items.reduce((sum, item) => sum + item.quantity, 0);
}
