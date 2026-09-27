/**
 * Mock data — admin sales (orders + invoices) para F5.4.
 *
 * Este archivo existe SOLO porque los endpoints `GET /api/v1/admin/orders`,
 * `GET /api/v1/admin/orders/{id}` y `GET /api/v1/admin/invoices` aún no
 * están implementados al cierre de F5.4. Cuando existan, las páginas
 * admin sales pasan de `getAllOrdersMock()` a `fetch()` con la misma
 * shape — la frontera es estable.
 *
 * Decisiones de shape:
 *  - `totalCents` en cents (entero) como manda AGENTS §10 — el
 *    formateo se hace en UI con `formatCurrency()`.
 *  - `status` matchea exactamente las variants del `Badge` primitive
 *    (design-refs §6.4): pending | processing | shipped | delivered
 *    | cancelled.
 *  - `timeline` usa los 4 hitos del admin (no 5 como account) — el
 *    admin quiere vista compacta received → processing → shipped →
 *    delivered. `cancelled` colapsa el timeline a solo `received`.
 *  - Invoices tienen status propio (draft / sent / paid / overdue /
 *    cancelled) independiente del status de la order, porque una
 *    order puede estar "shipped" pero su invoice todavía "sent"
 *    esperando pago.
 *
 * Cobertura:
 *  - 25 orders: 5 pending / 5 processing / 5 shipped / 7 delivered /
 *    3 cancelled (variedad en totals, items, customers).
 *  - 12 invoices: 3 draft / 2 sent / 5 paid / 2 overdue (mixed
 *    customers + due dates relativas a hoy).
 */

import type { OrderStatus } from './account-mock';

// ─── TYPES ─────────────────────────────────────────────────────────────

export type AdminOrderStatus = OrderStatus;

export type AdminInvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';

export type AdminCustomerType = 'retail' | 'wholesale' | 'vip';

export interface AdminCustomerMock {
  id: number;
  name: string;
  email: string;
  phone: string;
  type: AdminCustomerType;
  registeredAt: string; // ISO 8601 (date)
  lifetimeValueCents: number;
}

export interface AdminOrderItemMock {
  id: number;
  productName: string;
  productSku: string;
  quantity: number;
  unitPriceCents: number;
}

export interface AdminOrderShippingMock {
  recipient: string;
  phone: string;
  country: string;
  department: string;
  municipality: string;
  detailedAddress: string;
  reference?: string;
}

export interface AdminOrderPaymentMock {
  method: 'card' | 'transfer' | 'cash_on_delivery' | 'net30';
  last4?: string;
  brand?: string;
}

export interface AdminOrderTimelineEntry {
  key: 'received' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  timestamp: string; // ISO 8601
  actor: string;
  note?: string;
}

export interface AdminOrderMock {
  id: string;
  number: string;
  date: string; // ISO 8601
  status: AdminOrderStatus;
  subtotalCents: number;
  shippingCents: number;
  taxCents: number;
  totalCents: number;
  customer: AdminCustomerMock;
  items: AdminOrderItemMock[];
  shippingAddress: AdminOrderShippingMock;
  payment: AdminOrderPaymentMock;
  trackingNumber?: string;
  trackingCarrier?: string;
  trackingUrl?: string;
  timeline: AdminOrderTimelineEntry[];
  notes?: string;
}

export interface AdminInvoiceItemMock {
  description: string;
  quantity: number;
  unitPriceCents: number;
}

export interface AdminInvoiceMock {
  id: string;
  number: string;
  orderId: string;
  customer: AdminCustomerMock;
  status: AdminInvoiceStatus;
  issuedAt: string;
  dueAt: string;
  subtotalCents: number;
  taxCents: number;
  totalCents: number;
  items: AdminInvoiceItemMock[];
  notes?: string;
}

// ─── CATALOG ───────────────────────────────────────────────────────────

const CUSTOMERS: readonly AdminCustomerMock[] = [
  {
    id: 1,
    name: 'Carlos López',
    email: 'carlos.lopez@example.com',
    phone: '+505 8845 1122',
    type: 'retail',
    registeredAt: '2024-03-12',
    lifetimeValueCents: 145000,
  },
  {
    id: 2,
    name: 'Ana Martínez',
    email: 'ana.martinez@example.com',
    phone: '+505 8867 3344',
    type: 'wholesale',
    registeredAt: '2023-09-04',
    lifetimeValueCents: 1240000,
  },
  {
    id: 3,
    name: 'Roberto Sánchez',
    email: 'r.sanchez@example.com',
    phone: '+505 8823 9988',
    type: 'retail',
    registeredAt: '2025-01-18',
    lifetimeValueCents: 67000,
  },
  {
    id: 4,
    name: 'Laura Díaz',
    email: 'laura.diaz@example.com',
    phone: '+505 8899 7766',
    type: 'vip',
    registeredAt: '2022-06-30',
    lifetimeValueCents: 3580000,
  },
  {
    id: 5,
    name: 'Miguel Herrera',
    email: 'm.herrera@example.com',
    phone: '+505 8812 5544',
    type: 'retail',
    registeredAt: '2025-08-22',
    lifetimeValueCents: 23000,
  },
  {
    id: 6,
    name: 'Patricia Ruiz',
    email: 'p.ruiz@example.com',
    phone: '+505 8834 7788',
    type: 'wholesale',
    registeredAt: '2023-11-09',
    lifetimeValueCents: 890000,
  },
  {
    id: 7,
    name: 'Fernando Castro',
    email: 'f.castro@example.com',
    phone: '+505 8876 2233',
    type: 'retail',
    registeredAt: '2024-12-01',
    lifetimeValueCents: 156000,
  },
  {
    id: 8,
    name: 'Sofía Mendoza',
    email: 's.mendoza@example.com',
    phone: '+505 8845 6677',
    type: 'vip',
    registeredAt: '2021-04-14',
    lifetimeValueCents: 5220000,
  },
];

function customer(id: number): AdminCustomerMock {
  const found = CUSTOMERS.find((c) => c.id === id);
  if (!found) {
    throw new Error(`sales-mock: customer id ${id} not found in catalog`);
  }
  return found;
}

const PRODUCT_CATALOG: readonly { name: string; sku: string }[] = [
  { name: 'Camiseta Kitsune Edición Limitada', sku: 'KIT-TSHIRT-001' },
  { name: 'Taza personalizada Logo', sku: 'KIT-MUG-CUSTOM' },
  { name: 'Sudadera Kitsune Negra', sku: 'KIT-HOODIE-BLK' },
  { name: 'Gorra bordada', sku: 'KIT-CAP-EMB' },
  { name: 'Pack 3 playeras básicas', sku: 'KIT-TSHIRT-3PK' },
  { name: 'Figura Saber Alter Figma', sku: 'KIT-FIG-SABER' },
  { name: 'Tanjiro Kimono no Youjin', sku: 'KIT-FIG-TANJIRO' },
  { name: 'Sakura Card Captor Nendoroid', sku: 'KIT-FIG-SAKURA' },
  { name: 'Totoro Plush Medium', sku: 'KIT-PLUSH-TOTORO' },
  { name: 'Evangelion Unit 01 Manga T1', sku: 'KIT-MANGA-EVA01' },
];

function pickItem(catalogIndex: number, quantity: number): AdminOrderItemMock {
  const p = PRODUCT_CATALOG[catalogIndex];
  if (!p) {
    throw new Error(`sales-mock: product catalog index out of range ${catalogIndex}`);
  }
  return {
    id: (catalogIndex + 1) * 100 + quantity,
    productName: p.name,
    productSku: p.sku,
    quantity,
    unitPriceCents: 1000 + catalogIndex * 350,
  };
}

function buildTimeline(
  status: AdminOrderStatus,
  date: string,
): AdminOrderTimelineEntry[] {
  const HOUR = 60 * 60 * 1000;
  const orderTime = new Date(`${date}T12:00:00Z`).getTime();
  const at = (hoursBack: number): string =>
    new Date(orderTime - hoursBack * HOUR).toISOString();

  const base = (key: AdminOrderTimelineEntry['key'], h: number, actor: string): AdminOrderTimelineEntry => ({
    key,
    timestamp: at(h),
    actor,
  });

  if (status === 'cancelled') {
    return [
      base('received', 48, 'system'),
      { ...base('cancelled', 24, 'admin'), note: 'Cancelada por falta de stock' },
    ];
  }

  if (status === 'pending') {
    return [base('received', 6, 'system')];
  }

  if (status === 'processing') {
    return [base('received', 72, 'system'), base('processing', 36, 'admin')];
  }

  if (status === 'shipped') {
    return [
      base('received', 96, 'system'),
      base('processing', 72, 'admin'),
      { ...base('shipped', 8, 'admin'), note: 'Courier in transit to local hub' },
    ];
  }

  // delivered
  return [
    base('received', 168, 'system'),
    base('processing', 120, 'admin'),
    base('shipped', 72, 'admin'),
    base('delivered', 4, 'system'),
  ];
}

const DEFAULT_SHIPPING: AdminOrderShippingMock = {
  recipient: 'Daniel Pérez',
  phone: '+505 8888 1234',
  country: 'Nicaragua',
  department: 'Managua',
  municipality: 'Managua',
  detailedAddress: 'Residencias Bolonia, Calle las Rosas #14',
  reference: 'Casa esquinera, portón negro',
};

// ─── ORDERS (25) ──────────────────────────────────────────────────────

const ORDERS_DATA: readonly AdminOrderMock[] = [
  // ─── Delivered (7) ───────────────────────────────────────────────
  {
    id: '1042',
    number: '#ORD-1042',
    date: '2026-09-25',
    status: 'delivered',
    subtotalCents: 18500,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 19300,
    customer: customer(1),
    items: [pickItem(0, 1), pickItem(5, 1)],
    shippingAddress: DEFAULT_SHIPPING,
    payment: { method: 'card', brand: 'Visa', last4: '4242' },
    trackingNumber: 'NI7891234567',
    trackingCarrier: 'FedEx',
    trackingUrl: 'https://www.fedex.com/fedextrack/?trknum=NI7891234567',
    timeline: buildTimeline('delivered', '2026-09-25'),
  },
  {
    id: '1041',
    number: '#ORD-1041',
    date: '2026-09-22',
    status: 'delivered',
    subtotalCents: 4500,
    shippingCents: 600,
    taxCents: 0,
    totalCents: 5100,
    customer: customer(2),
    items: [pickItem(1, 2)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Ana Martínez' },
    payment: { method: 'transfer' },
    trackingNumber: 'NI1234567890',
    trackingCarrier: 'DHL',
    timeline: buildTimeline('delivered', '2026-09-22'),
  },
  {
    id: '1040',
    number: '#ORD-1040',
    date: '2026-09-20',
    status: 'delivered',
    subtotalCents: 29000,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 29000,
    customer: customer(4),
    items: [pickItem(0, 1), pickItem(7, 1), pickItem(8, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Laura Díaz', department: 'León' },
    payment: { method: 'card', brand: 'Mastercard', last4: '8888' },
    trackingNumber: 'NI0987654321',
    trackingCarrier: 'UPS',
    timeline: buildTimeline('delivered', '2026-09-20'),
  },
  {
    id: '1039',
    number: '#ORD-1039',
    date: '2026-09-18',
    status: 'delivered',
    subtotalCents: 8400,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 9200,
    customer: customer(3),
    items: [pickItem(2, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Roberto Sánchez', department: 'Granada' },
    payment: { method: 'cash_on_delivery' },
    trackingNumber: 'NI4567890123',
    trackingCarrier: 'DHL',
    timeline: buildTimeline('delivered', '2026-09-18'),
  },
  {
    id: '1038',
    number: '#ORD-1038',
    date: '2026-09-15',
    status: 'delivered',
    subtotalCents: 12300,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 13100,
    customer: customer(7),
    items: [pickItem(4, 1), pickItem(9, 2)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Fernando Castro' },
    payment: { method: 'card', brand: 'Visa', last4: '1111' },
    trackingNumber: 'NI5678901234',
    trackingCarrier: 'FedEx',
    timeline: buildTimeline('delivered', '2026-09-15'),
  },
  {
    id: '1037',
    number: '#ORD-1037',
    date: '2026-09-12',
    status: 'delivered',
    subtotalCents: 5400,
    shippingCents: 600,
    taxCents: 0,
    totalCents: 6000,
    customer: customer(5),
    items: [pickItem(3, 2)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Miguel Herrera', department: 'Rivas' },
    payment: { method: 'transfer' },
    trackingNumber: 'NI6789012345',
    trackingCarrier: 'UPS',
    timeline: buildTimeline('delivered', '2026-09-12'),
  },
  {
    id: '1036',
    number: '#ORD-1036',
    date: '2026-09-10',
    status: 'delivered',
    subtotalCents: 16700,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 17500,
    customer: customer(8),
    items: [pickItem(6, 1), pickItem(7, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Sofía Mendoza', department: 'Masaya' },
    payment: { method: 'net30' },
    trackingNumber: 'NI7890123456',
    trackingCarrier: 'FedEx',
    timeline: buildTimeline('delivered', '2026-09-10'),
  },

  // ─── Shipped (5) ─────────────────────────────────────────────────
  {
    id: '1035',
    number: '#ORD-1035',
    date: '2026-09-22',
    status: 'shipped',
    subtotalCents: 9900,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 10700,
    customer: customer(6),
    items: [pickItem(0, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Patricia Ruiz', department: 'Matagalpa' },
    payment: { method: 'card', brand: 'Visa', last4: '5555' },
    trackingNumber: 'NI8901234567',
    trackingCarrier: 'DHL',
    timeline: buildTimeline('shipped', '2026-09-22'),
  },
  {
    id: '1034',
    number: '#ORD-1034',
    date: '2026-09-21',
    status: 'shipped',
    subtotalCents: 7800,
    shippingCents: 600,
    taxCents: 0,
    totalCents: 8400,
    customer: customer(1),
    items: [pickItem(2, 1), pickItem(3, 1)],
    shippingAddress: DEFAULT_SHIPPING,
    payment: { method: 'card', brand: 'Visa', last4: '4242' },
    trackingNumber: 'NI9012345678',
    trackingCarrier: 'FedEx',
    timeline: buildTimeline('shipped', '2026-09-21'),
  },
  {
    id: '1033',
    number: '#ORD-1033',
    date: '2026-09-20',
    status: 'shipped',
    subtotalCents: 22000,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 22000,
    customer: customer(4),
    items: [pickItem(5, 1), pickItem(6, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Laura Díaz', department: 'León' },
    payment: { method: 'card', brand: 'Mastercard', last4: '8888' },
    trackingNumber: 'NI0123456789',
    trackingCarrier: 'UPS',
    timeline: buildTimeline('shipped', '2026-09-20'),
  },
  {
    id: '1032',
    number: '#ORD-1032',
    date: '2026-09-19',
    status: 'shipped',
    subtotalCents: 4500,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 5300,
    customer: customer(7),
    items: [pickItem(8, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Fernando Castro' },
    payment: { method: 'transfer' },
    trackingNumber: 'NI1357924680',
    trackingCarrier: 'DHL',
    timeline: buildTimeline('shipped', '2026-09-19'),
  },
  {
    id: '1031',
    number: '#ORD-1031',
    date: '2026-09-18',
    status: 'shipped',
    subtotalCents: 15000,
    shippingCents: 600,
    taxCents: 0,
    totalCents: 15600,
    customer: customer(8),
    items: [pickItem(7, 2)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Sofía Mendoza' },
    payment: { method: 'net30' },
    trackingNumber: 'NI2468013579',
    trackingCarrier: 'FedEx',
    timeline: buildTimeline('shipped', '2026-09-18'),
  },

  // ─── Processing (5) ──────────────────────────────────────────────
  {
    id: '1030',
    number: '#ORD-1030',
    date: '2026-09-23',
    status: 'processing',
    subtotalCents: 12000,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 12800,
    customer: customer(2),
    items: [pickItem(0, 2)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Ana Martínez' },
    payment: { method: 'transfer' },
    timeline: buildTimeline('processing', '2026-09-23'),
  },
  {
    id: '1029',
    number: '#ORD-1029',
    date: '2026-09-22',
    status: 'processing',
    subtotalCents: 6700,
    shippingCents: 600,
    taxCents: 0,
    totalCents: 7300,
    customer: customer(3),
    items: [pickItem(3, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Roberto Sánchez' },
    payment: { method: 'cash_on_delivery' },
    timeline: buildTimeline('processing', '2026-09-22'),
  },
  {
    id: '1028',
    number: '#ORD-1028',
    date: '2026-09-21',
    status: 'processing',
    subtotalCents: 25000,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 25000,
    customer: customer(4),
    items: [pickItem(5, 1), pickItem(6, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Laura Díaz', department: 'León' },
    payment: { method: 'card', brand: 'Mastercard', last4: '8888' },
    timeline: buildTimeline('processing', '2026-09-21'),
  },
  {
    id: '1027',
    number: '#ORD-1027',
    date: '2026-09-20',
    status: 'processing',
    subtotalCents: 5500,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 6300,
    customer: customer(5),
    items: [pickItem(1, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Miguel Herrera' },
    payment: { method: 'transfer' },
    timeline: buildTimeline('processing', '2026-09-20'),
  },
  {
    id: '1026',
    number: '#ORD-1026',
    date: '2026-09-19',
    status: 'processing',
    subtotalCents: 18900,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 19700,
    customer: customer(6),
    items: [pickItem(7, 1), pickItem(8, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Patricia Ruiz' },
    payment: { method: 'net30' },
    timeline: buildTimeline('processing', '2026-09-19'),
  },

  // ─── Pending (5) ─────────────────────────────────────────────────
  {
    id: '1025',
    number: '#ORD-1025',
    date: '2026-09-25',
    status: 'pending',
    subtotalCents: 8400,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 9200,
    customer: customer(1),
    items: [pickItem(2, 1)],
    shippingAddress: DEFAULT_SHIPPING,
    payment: { method: 'card', brand: 'Visa', last4: '4242' },
    timeline: buildTimeline('pending', '2026-09-25'),
  },
  {
    id: '1024',
    number: '#ORD-1024',
    date: '2026-09-24',
    status: 'pending',
    subtotalCents: 3300,
    shippingCents: 600,
    taxCents: 0,
    totalCents: 3900,
    customer: customer(7),
    items: [pickItem(4, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Fernando Castro', department: 'Estelí' },
    payment: { method: 'cash_on_delivery' },
    timeline: buildTimeline('pending', '2026-09-24'),
  },
  {
    id: '1023',
    number: '#ORD-1023',
    date: '2026-09-24',
    status: 'pending',
    subtotalCents: 23000,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 23000,
    customer: customer(8),
    items: [pickItem(5, 1), pickItem(7, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Sofía Mendoza' },
    payment: { method: 'net30' },
    timeline: buildTimeline('pending', '2026-09-24'),
  },
  {
    id: '1022',
    number: '#ORD-1022',
    date: '2026-09-23',
    status: 'pending',
    subtotalCents: 4500,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 5300,
    customer: customer(3),
    items: [pickItem(8, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Roberto Sánchez' },
    payment: { method: 'transfer' },
    timeline: buildTimeline('pending', '2026-09-23'),
  },
  {
    id: '1021',
    number: '#ORD-1021',
    date: '2026-09-22',
    status: 'pending',
    subtotalCents: 11000,
    shippingCents: 800,
    taxCents: 0,
    totalCents: 11800,
    customer: customer(4),
    items: [pickItem(0, 1), pickItem(2, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Laura Díaz' },
    payment: { method: 'card', brand: 'Mastercard', last4: '8888' },
    timeline: buildTimeline('pending', '2026-09-22'),
  },

  // ─── Cancelled (3) ──────────────────────────────────────────────
  {
    id: '1020',
    number: '#ORD-1020',
    date: '2026-09-20',
    status: 'cancelled',
    subtotalCents: 5400,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 0,
    customer: customer(5),
    items: [pickItem(1, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Miguel Herrera' },
    payment: { method: 'card', brand: 'Visa', last4: '9999' },
    timeline: buildTimeline('cancelled', '2026-09-20'),
    notes: 'Cliente canceló antes del despacho.',
  },
  {
    id: '1019',
    number: '#ORD-1019',
    date: '2026-09-15',
    status: 'cancelled',
    subtotalCents: 18900,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 0,
    customer: customer(6),
    items: [pickItem(6, 1), pickItem(7, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Patricia Ruiz' },
    payment: { method: 'net30' },
    timeline: buildTimeline('cancelled', '2026-09-15'),
    notes: 'Sin stock del SKU KIT-FIG-TANJIRO.',
  },
  {
    id: '1018',
    number: '#ORD-1018',
    date: '2026-09-10',
    status: 'cancelled',
    subtotalCents: 4500,
    shippingCents: 0,
    taxCents: 0,
    totalCents: 0,
    customer: customer(2),
    items: [pickItem(3, 1)],
    shippingAddress: { ...DEFAULT_SHIPPING, recipient: 'Ana Martínez' },
    payment: { method: 'transfer' },
    timeline: buildTimeline('cancelled', '2026-09-10'),
    notes: 'Pago no recibido en 48 h.',
  },
];

// ─── INVOICES (12) ───────────────────────────────────────────────────

const INVOICES_DATA: readonly AdminInvoiceMock[] = [
  // ─── Draft (3) ──────────────────────────────────────────────────
  {
    id: 'inv-001',
    number: 'INV-2026-001',
    orderId: '1030',
    customer: customer(2),
    status: 'draft',
    issuedAt: '2026-09-23',
    dueAt: '2026-10-23',
    subtotalCents: 12000,
    taxCents: 0,
    totalCents: 12800,
    items: [{ description: 'Camiseta Kitsune Edición Limitada × 2', quantity: 2, unitPriceCents: 6000 }],
    notes: 'Pendiente de revisar totales antes de enviar.',
  },
  {
    id: 'inv-002',
    number: 'INV-2026-002',
    orderId: '1029',
    customer: customer(3),
    status: 'draft',
    issuedAt: '2026-09-22',
    dueAt: '2026-10-22',
    subtotalCents: 6700,
    taxCents: 0,
    totalCents: 7300,
    items: [{ description: 'Gorra bordada', quantity: 1, unitPriceCents: 6700 }],
  },
  {
    id: 'inv-003',
    number: 'INV-2026-003',
    orderId: '1025',
    customer: customer(1),
    status: 'draft',
    issuedAt: '2026-09-25',
    dueAt: '2026-10-25',
    subtotalCents: 8400,
    taxCents: 0,
    totalCents: 9200,
    items: [{ description: 'Sudadera Kitsune Negra', quantity: 1, unitPriceCents: 8400 }],
  },

  // ─── Sent (2) ───────────────────────────────────────────────────
  {
    id: 'inv-004',
    number: 'INV-2026-004',
    orderId: '1033',
    customer: customer(4),
    status: 'sent',
    issuedAt: '2026-09-20',
    dueAt: '2026-10-20',
    subtotalCents: 22000,
    taxCents: 0,
    totalCents: 22000,
    items: [
      { description: 'Figura Saber Alter Figma', quantity: 1, unitPriceCents: 11000 },
      { description: 'Figura Tanjiro Kimono', quantity: 1, unitPriceCents: 11000 },
    ],
  },
  {
    id: 'inv-005',
    number: 'INV-2026-005',
    orderId: '1026',
    customer: customer(6),
    status: 'sent',
    issuedAt: '2026-09-19',
    dueAt: '2026-10-19',
    subtotalCents: 18900,
    taxCents: 0,
    totalCents: 19700,
    items: [
      { description: 'Sakura Card Captor Nendoroid', quantity: 1, unitPriceCents: 9500 },
      { description: 'Totoro Plush Medium', quantity: 1, unitPriceCents: 9400 },
    ],
  },

  // ─── Paid (5) ───────────────────────────────────────────────────
  {
    id: 'inv-006',
    number: 'INV-2026-006',
    orderId: '1042',
    customer: customer(1),
    status: 'paid',
    issuedAt: '2026-09-25',
    dueAt: '2026-10-25',
    subtotalCents: 18500,
    taxCents: 0,
    totalCents: 19300,
    items: [
      { description: 'Camiseta Kitsune Edición Limitada', quantity: 1, unitPriceCents: 8500 },
      { description: 'Figura Saber Alter Figma', quantity: 1, unitPriceCents: 10000 },
    ],
  },
  {
    id: 'inv-007',
    number: 'INV-2026-007',
    orderId: '1041',
    customer: customer(2),
    status: 'paid',
    issuedAt: '2026-09-22',
    dueAt: '2026-10-22',
    subtotalCents: 4500,
    taxCents: 0,
    totalCents: 5100,
    items: [{ description: 'Taza personalizada Logo × 2', quantity: 2, unitPriceCents: 2250 }],
  },
  {
    id: 'inv-008',
    number: 'INV-2026-008',
    orderId: '1040',
    customer: customer(4),
    status: 'paid',
    issuedAt: '2026-09-20',
    dueAt: '2026-10-20',
    subtotalCents: 29000,
    taxCents: 0,
    totalCents: 29000,
    items: [
      { description: 'Camiseta Kitsune Edición Limitada', quantity: 1, unitPriceCents: 8500 },
      { description: 'Sakura Card Captor Nendoroid', quantity: 1, unitPriceCents: 9500 },
      { description: 'Totoro Plush Medium', quantity: 1, unitPriceCents: 11000 },
    ],
  },
  {
    id: 'inv-009',
    number: 'INV-2026-009',
    orderId: '1039',
    customer: customer(3),
    status: 'paid',
    issuedAt: '2026-09-18',
    dueAt: '2026-10-18',
    subtotalCents: 8400,
    taxCents: 0,
    totalCents: 9200,
    items: [{ description: 'Sudadera Kitsune Negra', quantity: 1, unitPriceCents: 8400 }],
  },
  {
    id: 'inv-010',
    number: 'INV-2026-010',
    orderId: '1036',
    customer: customer(8),
    status: 'paid',
    issuedAt: '2026-09-10',
    dueAt: '2026-10-10',
    subtotalCents: 16700,
    taxCents: 0,
    totalCents: 17500,
    items: [
      { description: 'Figura Tanjiro Kimono', quantity: 1, unitPriceCents: 11000 },
      { description: 'Sakura Card Captor Nendoroid', quantity: 1, unitPriceCents: 5700 },
    ],
  },

  // ─── Overdue (2) ────────────────────────────────────────────────
  {
    id: 'inv-011',
    number: 'INV-2026-011',
    orderId: '1038',
    customer: customer(7),
    status: 'overdue',
    issuedAt: '2026-08-15',
    dueAt: '2026-09-15',
    subtotalCents: 12300,
    taxCents: 0,
    totalCents: 13100,
    items: [
      { description: 'Pack 3 playeras básicas', quantity: 1, unitPriceCents: 4500 },
      { description: 'Evangelion Unit 01 Manga T1 × 2', quantity: 2, unitPriceCents: 3900 },
    ],
    notes: 'Vencida hace 10 días. Enviar recordatorio.',
  },
  {
    id: 'inv-012',
    number: 'INV-2026-012',
    orderId: '1037',
    customer: customer(5),
    status: 'overdue',
    issuedAt: '2026-08-12',
    dueAt: '2026-09-12',
    subtotalCents: 5400,
    taxCents: 0,
    totalCents: 6000,
    items: [{ description: 'Gorra bordada × 2', quantity: 2, unitPriceCents: 2700 }],
    notes: 'Cliente confirmó pago pero no se ha recibido.',
  },
];

// ─── PUBLIC API ───────────────────────────────────────────────────────

export const ORDERS_MOCK: readonly AdminOrderMock[] = ORDERS_DATA;
export const INVOICES_MOCK: readonly AdminInvoiceMock[] = INVOICES_DATA;

export function getAllOrdersMock(): readonly AdminOrderMock[] {
  return ORDERS_MOCK;
}

export function findOrderByIdMock(id: string): AdminOrderMock | null {
  return ORDERS_MOCK.find((order) => order.id === id) ?? null;
}

export function countOrderItemsMock(order: AdminOrderMock): number {
  return order.items.reduce((sum, item) => sum + item.quantity, 0);
}

export function getAllInvoicesMock(): readonly AdminInvoiceMock[] {
  return INVOICES_MOCK;
}

export function findInvoiceByIdMock(id: string): AdminInvoiceMock | null {
  return INVOICES_MOCK.find((invoice) => invoice.id === id) ?? null;
}

export function findInvoiceByOrderIdMock(orderId: string): AdminInvoiceMock | null {
  return INVOICES_MOCK.find((invoice) => invoice.orderId === orderId) ?? null;
}