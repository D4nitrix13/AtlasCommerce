/**
 * Mock data para el módulo de Returns admin.
 *
 * 15 solicitudes de devolución con variedad de status para que la UI
 * renderice todas las pills + filtros sin sentirse vacía.
 *
 * Status sigue la convención ya establecida en
 * `frontend/src/types/returns.ts`:
 *   pending   → recién creada, esperando revisión del admin
 *   approved  → aprobada, habilita creación de refund
 *   rejected  → rechazada (terminal)
 *   completed → refund procesado (terminal)
 *   cancelled → cancelada por el cliente (terminal)
 *
 * Mismo patrón que `support-mock.ts`: tipos exportados + array
 * congelado con `as const`. Cuando el backend exponga
 * `/api/v1/admin/returns`, los componentes hacen `Astro.fetch()`
 * con fallback a este mock.
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading.
 */

import type { ReturnRequestStatus } from '../types/returns';

export interface ReturnRequestMock {
  readonly id: string;
  readonly orderNumber: string;
  readonly customerName: string;
  readonly reason: string;
  readonly status: ReturnRequestStatus;
  readonly requestedAt: string;
  readonly resolvedAt: string | null;
  readonly amountCents: number;
}

export const returnRequests: readonly ReturnRequestMock[] = [
  {
    id: 'ret-001',
    orderNumber: '#ORD-1432',
    customerName: 'Patricia Ruiz',
    reason: 'Producto defectuoso — taza con la impresión borrosa',
    status: 'pending',
    requestedAt: '2026-09-26T09:20:00Z',
    resolvedAt: null,
    amountCents: 1500,
  },
  {
    id: 'ret-002',
    orderNumber: '#ORD-1418',
    customerName: 'Carlos López',
    reason: 'Cambio de talla — sudadera XS por S',
    status: 'pending',
    requestedAt: '2026-09-25T18:40:00Z',
    resolvedAt: null,
    amountCents: 0,
  },
  {
    id: 'ret-003',
    orderNumber: '#ORD-1405',
    customerName: 'Jorge Vega',
    reason: 'No coincide con la descripción — color más oscuro que en la foto',
    status: 'approved',
    requestedAt: '2026-09-25T14:10:00Z',
    resolvedAt: null,
    amountCents: 2800,
  },
  {
    id: 'ret-004',
    orderNumber: '#ORD-1398',
    customerName: 'Lucía Mendoza',
    reason: 'Personalización mal escrita — dice "LUSIA" en vez de "LUCÍA"',
    status: 'approved',
    requestedAt: '2026-09-24T11:30:00Z',
    resolvedAt: null,
    amountCents: 2200,
  },
  {
    id: 'ret-005',
    orderNumber: '#ORD-1392',
    customerName: 'Ana Martínez',
    reason: 'Hoodie llegó con manchas blancas en la manga',
    status: 'completed',
    requestedAt: '2026-09-23T16:42:00Z',
    resolvedAt: '2026-09-25T09:15:00Z',
    amountCents: 4500,
  },
  {
    id: 'ret-006',
    orderNumber: '#ORD-1389',
    customerName: 'Roberto Sánchez',
    reason: 'Cliente arrepentido — dentro de los 7 días',
    status: 'completed',
    requestedAt: '2026-09-22T13:00:00Z',
    resolvedAt: '2026-09-23T10:30:00Z',
    amountCents: 3200,
  },
  {
    id: 'ret-007',
    orderNumber: '#ORD-1375',
    customerName: 'Miguel Herrera',
    reason: 'Producto usado/envuelto — no aplica devolución',
    status: 'rejected',
    requestedAt: '2026-09-21T10:15:00Z',
    resolvedAt: '2026-09-22T14:00:00Z',
    amountCents: 0,
  },
  {
    id: 'ret-008',
    orderNumber: '#ORD-1360',
    customerName: 'Sofía Aguilar',
    reason: 'Stickers despegados al primer uso',
    status: 'completed',
    requestedAt: '2026-09-20T15:00:00Z',
    resolvedAt: '2026-09-21T11:30:00Z',
    amountCents: 850,
  },
  {
    id: 'ret-009',
    orderNumber: '#ORD-1345',
    customerName: 'Diego Castro',
    reason: 'Botella térmica — tapa gotea y moja la mochila',
    status: 'pending',
    requestedAt: '2026-09-19T08:45:00Z',
    resolvedAt: null,
    amountCents: 1800,
  },
  {
    id: 'ret-010',
    orderNumber: '#ORD-1320',
    customerName: 'Valentina Cruz',
    reason: 'Mochila con cierre defectuoso',
    status: 'approved',
    requestedAt: '2026-09-18T17:30:00Z',
    resolvedAt: null,
    amountCents: 3500,
  },
  {
    id: 'ret-011',
    orderNumber: '#ORD-1298',
    customerName: 'Laura Díaz',
    reason: 'Cliente cambió de opinión',
    status: 'cancelled',
    requestedAt: '2026-09-17T12:00:00Z',
    resolvedAt: '2026-09-17T18:00:00Z',
    amountCents: 0,
  },
  {
    id: 'ret-012',
    orderNumber: '#ORD-1285',
    customerName: 'Patricia Ruiz',
    reason: 'Lanyard pixelado — calidad no aceptable',
    status: 'completed',
    requestedAt: '2026-09-15T09:30:00Z',
    resolvedAt: '2026-09-16T14:00:00Z',
    amountCents: 450,
  },
  {
    id: 'ret-013',
    orderNumber: '#ORD-1262',
    customerName: 'Fernando Castro',
    reason: 'Cuaderno con portada rayada al recibirlo',
    status: 'rejected',
    requestedAt: '2026-09-14T10:20:00Z',
    resolvedAt: '2026-09-15T16:00:00Z',
    amountCents: 0,
  },
  {
    id: 'ret-014',
    orderNumber: '#ORD-1240',
    customerName: 'Roberto Sánchez',
    reason: 'Calendario con hojas en blanco — error de impresión',
    status: 'completed',
    requestedAt: '2026-09-12T11:45:00Z',
    resolvedAt: '2026-09-13T15:30:00Z',
    amountCents: 1200,
  },
  {
    id: 'ret-015',
    orderNumber: '#ORD-1225',
    customerName: 'Ana Martínez',
    reason: 'Mousepad demasiado pequeño para mi setup',
    status: 'pending',
    requestedAt: '2026-09-10T14:30:00Z',
    resolvedAt: null,
    amountCents: 2100,
  },
] as const;
