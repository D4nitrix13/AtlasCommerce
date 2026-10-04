/**
 * Mock data para el módulo de Purchases admin (B2B compras a proveedores).
 *
 * Tres dominios:
 *  - suppliers[]    : proveedores activos e inactivos (8 items)
 *  - purchaseOrders[] : órdenes de compra a proveedores (15 items)
 *
 * Status de purchase orders sigue el flujo B2B típico:
 *   pending   → creada, esperando aprobación del admin
 *   approved  → aprobada, enviada al supplier
 *   shipped   → en tránsito desde el supplier
 *   received  → recibida en almacén (terminal feliz)
 *   cancelled → cancelada (terminal)
 *
 * Status de supplier:
 *   active    → con órdenes en los últimos 90 días
 *   inactive  → sin órdenes recientes o bloqueado
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading.
 */

export type PurchaseOrderStatus = 'pending' | 'approved' | 'shipped' | 'received' | 'cancelled';

export type SupplierStatus = 'active' | 'inactive';

export interface Supplier {
  readonly id: string;
  readonly name: string;
  readonly contactName: string;
  readonly contactEmail: string;
  readonly contactPhone: string;
  readonly country: string;
  readonly totalPurchasedCents: number;
  readonly ordersCount: number;
  readonly status: SupplierStatus;
}

export interface PurchaseOrder {
  readonly id: string;
  readonly number: string;
  readonly supplierId: string;
  readonly supplierName: string;
  readonly itemsCount: number;
  readonly totalCents: number;
  readonly status: PurchaseOrderStatus;
  readonly placedAt: string;
  readonly expectedAt: string | null;
  readonly receivedAt: string | null;
}

export const suppliers: readonly Supplier[] = [
  {
    id: 'sup-001',
    name: 'Textiles del Valle S.A.',
    contactName: 'Mario Mendoza',
    contactEmail: 'mario@textilesvalle.example',
    contactPhone: '+505 2244 8800',
    country: 'Nicaragua',
    totalPurchasedCents: 4_580_000,
    ordersCount: 18,
    status: 'active',
  },
  {
    id: 'sup-002',
    name: 'Bordados Premium MX',
    contactName: 'Lucía Hernández',
    contactEmail: 'lucia@bordadospremium.example',
    contactPhone: '+52 55 1234 5678',
    country: 'México',
    totalPurchasedCents: 3_120_000,
    ordersCount: 12,
    status: 'active',
  },
  {
    id: 'sup-003',
    name: 'Cerámica Artesanal CR',
    contactName: 'Andrés Solís',
    contactEmail: 'andres@ceramicaartesanal.example',
    contactPhone: '+506 2233 4455',
    country: 'Costa Rica',
    totalPurchasedCents: 2_850_000,
    ordersCount: 9,
    status: 'active',
  },
  {
    id: 'sup-004',
    name: 'Papelera Industrial GT',
    contactName: 'Carmen Reyes',
    contactEmail: 'carmen@papelera-industrial.example',
    contactPhone: '+502 2334 5566',
    country: 'Guatemala',
    totalPurchasedCents: 1_640_000,
    ordersCount: 7,
    status: 'active',
  },
  {
    id: 'sup-005',
    name: 'Sublimación Global SV',
    contactName: 'José Pérez',
    contactEmail: 'jose@sublimacion-global.example',
    contactPhone: '+503 2244 6677',
    country: 'El Salvador',
    totalPurchasedCents: 980_000,
    ordersCount: 5,
    status: 'active',
  },
  {
    id: 'sup-006',
    name: 'Tintas y Resinas HN',
    contactName: 'Roberto Flores',
    contactEmail: 'r.flores@tintas-resinas.example',
    contactPhone: '+504 2233 7788',
    country: 'Honduras',
    totalPurchasedCents: 420_000,
    ordersCount: 3,
    status: 'active',
  },
  {
    id: 'sup-007',
    name: 'Mercería Mayorista PA',
    contactName: 'Diana Castro',
    contactEmail: 'diana@merceria-mayorista.example',
    contactPhone: '+507 2300 1122',
    country: 'Panamá',
    totalPurchasedCents: 180_000,
    ordersCount: 2,
    status: 'inactive',
  },
  {
    id: 'sup-008',
    name: 'Empaques del Caribe CO',
    contactName: 'Felipe Vargas',
    contactEmail: 'felipe@empaques-caribe.example',
    contactPhone: '+57 1 234 5678',
    country: 'Colombia',
    totalPurchasedCents: 0,
    ordersCount: 0,
    status: 'inactive',
  },
] as const;

export const purchaseOrders: readonly PurchaseOrder[] = [
  {
    id: 'po-001',
    number: '#PO-245',
    supplierId: 'sup-001',
    supplierName: 'Textiles del Valle S.A.',
    itemsCount: 24,
    totalCents: 2_480_000,
    status: 'received',
    placedAt: '2026-09-12T10:00:00Z',
    expectedAt: '2026-09-22T00:00:00Z',
    receivedAt: '2026-09-21T14:30:00Z',
  },
  {
    id: 'po-002',
    number: '#PO-246',
    supplierId: 'sup-002',
    supplierName: 'Bordados Premium MX',
    itemsCount: 12,
    totalCents: 1_350_000,
    status: 'shipped',
    placedAt: '2026-09-18T09:30:00Z',
    expectedAt: '2026-09-28T00:00:00Z',
    receivedAt: null,
  },
  {
    id: 'po-003',
    number: '#PO-247',
    supplierId: 'sup-003',
    supplierName: 'Cerámica Artesanal CR',
    itemsCount: 50,
    totalCents: 580_000,
    status: 'shipped',
    placedAt: '2026-09-19T11:00:00Z',
    expectedAt: '2026-09-27T00:00:00Z',
    receivedAt: null,
  },
  {
    id: 'po-004',
    number: '#PO-248',
    supplierId: 'sup-004',
    supplierName: 'Papelera Industrial GT',
    itemsCount: 30,
    totalCents: 420_000,
    status: 'approved',
    placedAt: '2026-09-20T14:00:00Z',
    expectedAt: '2026-10-01T00:00:00Z',
    receivedAt: null,
  },
  {
    id: 'po-005',
    number: '#PO-249',
    supplierId: 'sup-005',
    supplierName: 'Sublimación Global SV',
    itemsCount: 15,
    totalCents: 285_000,
    status: 'approved',
    placedAt: '2026-09-22T08:00:00Z',
    expectedAt: '2026-10-02T00:00:00Z',
    receivedAt: null,
  },
  {
    id: 'po-006',
    number: '#PO-250',
    supplierId: 'sup-001',
    supplierName: 'Textiles del Valle S.A.',
    itemsCount: 18,
    totalCents: 1_890_000,
    status: 'pending',
    placedAt: '2026-09-25T16:30:00Z',
    expectedAt: null,
    receivedAt: null,
  },
  {
    id: 'po-007',
    number: '#PO-251',
    supplierId: 'sup-002',
    supplierName: 'Bordados Premium MX',
    itemsCount: 8,
    totalCents: 720_000,
    status: 'pending',
    placedAt: '2026-09-25T17:15:00Z',
    expectedAt: null,
    receivedAt: null,
  },
  {
    id: 'po-008',
    number: '#PO-252',
    supplierId: 'sup-003',
    supplierName: 'Cerámica Artesanal CR',
    itemsCount: 40,
    totalCents: 460_000,
    status: 'pending',
    placedAt: '2026-09-26T09:00:00Z',
    expectedAt: null,
    receivedAt: null,
  },
  {
    id: 'po-009',
    number: '#PO-253',
    supplierId: 'sup-001',
    supplierName: 'Textiles del Valle S.A.',
    itemsCount: 32,
    totalCents: 3_120_000,
    status: 'received',
    placedAt: '2026-08-28T10:30:00Z',
    expectedAt: '2026-09-08T00:00:00Z',
    receivedAt: '2026-09-07T11:30:00Z',
  },
  {
    id: 'po-010',
    number: '#PO-254',
    supplierId: 'sup-006',
    supplierName: 'Tintas y Resinas HN',
    itemsCount: 20,
    totalCents: 320_000,
    status: 'received',
    placedAt: '2026-08-25T14:00:00Z',
    expectedAt: '2026-09-05T00:00:00Z',
    receivedAt: '2026-09-04T16:00:00Z',
  },
  {
    id: 'po-011',
    number: '#PO-255',
    supplierId: 'sup-004',
    supplierName: 'Papelera Industrial GT',
    itemsCount: 25,
    totalCents: 380_000,
    status: 'received',
    placedAt: '2026-08-20T09:00:00Z',
    expectedAt: '2026-09-01T00:00:00Z',
    receivedAt: '2026-08-31T15:30:00Z',
  },
  {
    id: 'po-012',
    number: '#PO-256',
    supplierId: 'sup-005',
    supplierName: 'Sublimación Global SV',
    itemsCount: 10,
    totalCents: 195_000,
    status: 'cancelled',
    placedAt: '2026-08-18T11:00:00Z',
    expectedAt: null,
    receivedAt: null,
  },
  {
    id: 'po-013',
    number: '#PO-257',
    supplierId: 'sup-002',
    supplierName: 'Bordados Premium MX',
    itemsCount: 6,
    totalCents: 540_000,
    status: 'cancelled',
    placedAt: '2026-08-15T13:00:00Z',
    expectedAt: null,
    receivedAt: null,
  },
  {
    id: 'po-014',
    number: '#PO-258',
    supplierId: 'sup-001',
    supplierName: 'Textiles del Valle S.A.',
    itemsCount: 28,
    totalCents: 2_750_000,
    status: 'received',
    placedAt: '2026-08-10T08:00:00Z',
    expectedAt: '2026-08-20T00:00:00Z',
    receivedAt: '2026-08-19T10:00:00Z',
  },
  {
    id: 'po-015',
    number: '#PO-259',
    supplierId: 'sup-003',
    supplierName: 'Cerámica Artesanal CR',
    itemsCount: 36,
    totalCents: 410_000,
    status: 'received',
    placedAt: '2026-08-05T12:00:00Z',
    expectedAt: '2026-08-15T00:00:00Z',
    receivedAt: '2026-08-14T14:00:00Z',
  },
] as const;
