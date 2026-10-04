/**
 * Mock data para el módulo de System admin (notificaciones + audit log).
 *
 * Dos dominios:
 *  - notifications[]  : eventos recientes que recibe el admin (10 items)
 *  - auditLog[]       : log de auditoría de cambios (25 entries)
 *
 * Mismo patrón que `marketing-mock.ts`: tipos exportados + arrays
 * congelados con `as const`. Cuando el backend exponga
 * `/api/v1/admin/system/*`, los componentes hacen `Astro.fetch()` con
 * fallback a este mock.
 *
 * Notification kind agrupa los 4 disparadores típicos del admin:
 *   order_placed    → nueva orden requiere atención
 *   return_request  → cliente pidió devolución
 *   ticket_reply    → cliente respondió un ticket
 *   low_stock       → producto bajo de stock
 *
 * Audit log action sigue convención `<resource>.<verb>` para que sea
 * fácil filtrar por dominio (ej. `order.*`, `product.*`, `user.*`).
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading.
 */

export type NotificationKind = 'orderPlaced' | 'returnRequest' | 'ticketReply' | 'lowStock';

export type NotificationChannel = 'email' | 'in_app';

export interface NotificationPreference {
  readonly channel: NotificationChannel;
  readonly orderPlaced: boolean;
  readonly returnRequest: boolean;
  readonly ticketReply: boolean;
  readonly lowStock: boolean;
}

export interface SystemNotification {
  readonly id: string;
  readonly kind: NotificationKind;
  readonly title: string;
  readonly body: string;
  readonly createdAt: string;
  readonly isRead: boolean;
  readonly link: string;
}

export type AuditResult = 'success' | 'failure';

export interface AuditLogEntry {
  readonly id: string;
  readonly timestamp: string;
  readonly userEmail: string;
  readonly action: string;
  readonly resource: string;
  readonly ipAddress: string;
  readonly result: AuditResult;
  readonly detail: string;
}

export const systemNotifications: readonly SystemNotification[] = [
  {
    id: 'notif-001',
    kind: 'orderPlaced',
    title: 'Nueva orden #ORD-1432',
    body: 'Sofía Mendoza acaba de hacer un pedido por $485.50 (3 items).',
    createdAt: '2026-09-26T09:45:00Z',
    isRead: false,
    link: '/es/admin/sales/orders/ord-1432',
  },
  {
    id: 'notif-002',
    kind: 'returnRequest',
    title: 'Solicitud de devolución #RET-018',
    body: 'Patricia Ruiz pidió devolución del pedido #ORD-1398. Razón: producto defectuoso.',
    createdAt: '2026-09-26T09:20:00Z',
    isRead: false,
    link: '/es/admin/returns',
  },
  {
    id: 'notif-003',
    kind: 'lowStock',
    title: 'Stock bajo: Camiseta Kitsune Negra M',
    body: 'Quedan 3 unidades en Almacén Central. Threshold: 10.',
    createdAt: '2026-09-26T08:55:00Z',
    isRead: false,
    link: '/es/admin/inventory/stock',
  },
  {
    id: 'notif-004',
    kind: 'ticketReply',
    title: 'Respuesta en ticket #SUP-1042',
    body: 'Carlos López respondió al ticket sobre la camiseta rota.',
    createdAt: '2026-09-26T08:14:00Z',
    isRead: true,
    link: '/es/admin/support/tickets',
  },
  {
    id: 'notif-005',
    kind: 'orderPlaced',
    title: 'Nueva orden #ORD-1431',
    body: 'Roberto Sánchez hizo un pedido por $1,250.00 (12 playeras al mayor).',
    createdAt: '2026-09-26T07:30:00Z',
    isRead: true,
    link: '/es/admin/sales/orders/ord-1431',
  },
  {
    id: 'notif-006',
    kind: 'lowStock',
    title: 'Stock bajo: Taza Personalizada Logo',
    body: 'Quedan 5 unidades. Threshold: 15.',
    createdAt: '2026-09-25T22:10:00Z',
    isRead: true,
    link: '/es/admin/inventory/stock',
  },
  {
    id: 'notif-007',
    kind: 'returnRequest',
    title: 'Solicitud de devolución #RET-017',
    body: 'Jorge Vega pidió cambio de talla de la sudadera.',
    createdAt: '2026-09-25T18:40:00Z',
    isRead: true,
    link: '/es/admin/returns',
  },
  {
    id: 'notif-008',
    kind: 'ticketReply',
    title: 'Respuesta en ticket #SUP-1038',
    body: 'Patricia Ruiz confirmó que el cupón ya funcionó.',
    createdAt: '2026-09-25T17:00:00Z',
    isRead: true,
    link: '/es/admin/support/tickets',
  },
  {
    id: 'notif-009',
    kind: 'orderPlaced',
    title: 'Nueva orden #ORD-1428',
    body: 'ACME S.A. hizo pedido corporativo por $4,580.00 (200 uniformes).',
    createdAt: '2026-09-25T14:25:00Z',
    isRead: true,
    link: '/es/admin/sales/orders/ord-1428',
  },
  {
    id: 'notif-010',
    kind: 'lowStock',
    title: 'Stock agotado: Memoria USB 16GB Madera',
    body: 'Quedan 0 unidades en todos los depósitos.',
    createdAt: '2026-09-25T11:00:00Z',
    isRead: true,
    link: '/es/admin/inventory/stock',
  },
] as const;

export const notificationPreferences: readonly NotificationPreference[] = [
  {
    channel: 'email',
    orderPlaced: true,
    returnRequest: true,
    ticketReply: true,
    lowStock: true,
  },
  {
    channel: 'in_app',
    orderPlaced: true,
    returnRequest: true,
    ticketReply: true,
    lowStock: false,
  },
] as const;

export const auditLog: readonly AuditLogEntry[] = [
  {
    id: 'aud-001',
    timestamp: '2026-09-26T09:45:12Z',
    userEmail: 'admin@kitsune.example',
    action: 'order.create',
    resource: 'order/ORD-1432',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Order creada con 3 items, total $485.50',
  },
  {
    id: 'aud-002',
    timestamp: '2026-09-26T09:20:45Z',
    userEmail: 'admin@kitsune.example',
    action: 'return.create',
    resource: 'return/RET-018',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Solicitud de devolución creada por cliente Patricia Ruiz',
  },
  {
    id: 'aud-003',
    timestamp: '2026-09-26T08:55:30Z',
    userEmail: 'system',
    action: 'stock.threshold_breached',
    resource: 'product/p-001',
    ipAddress: '127.0.0.1',
    result: 'success',
    detail: 'Stock de variante SKU-001-M-NE cayó a 3 unidades (threshold 10)',
  },
  {
    id: 'aud-004',
    timestamp: '2026-09-26T08:14:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'ticket.reply',
    resource: 'ticket/SUP-1042',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Respuesta enviada al cliente sobre camiseta rota',
  },
  {
    id: 'aud-005',
    timestamp: '2026-09-26T07:32:15Z',
    userEmail: 'admin@kitsune.example',
    action: 'product.update',
    resource: 'product/p-024',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Precio actualizado de $25.00 a $27.50',
  },
  {
    id: 'aud-006',
    timestamp: '2026-09-26T07:30:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'order.create',
    resource: 'order/ORD-1431',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Order corporativa creada con 12 items, total $1,250.00',
  },
  {
    id: 'aud-007',
    timestamp: '2026-09-26T05:18:42Z',
    userEmail: 'admin2@kitsune.example',
    action: 'user.login',
    resource: 'user/u-002',
    ipAddress: '10.0.0.45',
    result: 'failure',
    detail: 'Credenciales inválidas (3er intento fallido)',
  },
  {
    id: 'aud-008',
    timestamp: '2026-09-25T22:10:00Z',
    userEmail: 'system',
    action: 'stock.threshold_breached',
    resource: 'product/p-003',
    ipAddress: '127.0.0.1',
    result: 'success',
    detail: 'Stock de Taza Personalizada cayó a 5 unidades (threshold 15)',
  },
  {
    id: 'aud-009',
    timestamp: '2026-09-25T18:40:12Z',
    userEmail: 'admin@kitsune.example',
    action: 'return.create',
    resource: 'return/RET-017',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Solicitud de cambio de talla creada por cliente Jorge Vega',
  },
  {
    id: 'aud-010',
    timestamp: '2026-09-25T17:00:30Z',
    userEmail: 'admin@kitsune.example',
    action: 'ticket.resolve',
    resource: 'ticket/SUP-1038',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Ticket marcado como resuelto (cupón BLACKFRIDAY aplicado)',
  },
  {
    id: 'aud-011',
    timestamp: '2026-09-25T15:45:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'coupon.create',
    resource: 'coupon/VERANO2026',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Cupón VERANO2026 creado con 15% descuento',
  },
  {
    id: 'aud-012',
    timestamp: '2026-09-25T14:25:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'order.create',
    resource: 'order/ORD-1428',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Pedido corporativo ACME S.A. por $4,580.00',
  },
  {
    id: 'aud-013',
    timestamp: '2026-09-25T11:30:18Z',
    userEmail: 'admin@kitsune.example',
    action: 'product.delete',
    resource: 'product/p-099',
    ipAddress: '192.168.1.10',
    result: 'failure',
    detail: 'Intento de borrado bloqueado: producto tiene 12 orders activos',
  },
  {
    id: 'aud-014',
    timestamp: '2026-09-25T11:00:00Z',
    userEmail: 'system',
    action: 'stock.out_of_stock',
    resource: 'product/p-017',
    ipAddress: '127.0.0.1',
    result: 'success',
    detail: 'Memoria USB 16GB Madera agotada en todos los depósitos',
  },
  {
    id: 'aud-015',
    timestamp: '2026-09-25T09:20:45Z',
    userEmail: 'admin@kitsune.example',
    action: 'review.approve',
    resource: 'review/rev-003',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Reseña aprobada y publicada (rating 5)',
  },
  {
    id: 'aud-016',
    timestamp: '2026-09-25T08:15:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'settings.update',
    resource: 'settings/tax-rates',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Tasa de IVA Nicaragua actualizada de 15% a 15.5%',
  },
  {
    id: 'aud-017',
    timestamp: '2026-09-24T19:42:30Z',
    userEmail: 'admin@kitsune.example',
    action: 'role.assign',
    resource: 'user/u-005',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Rol "support_agent" asignado a Patricia Ruiz',
  },
  {
    id: 'aud-018',
    timestamp: '2026-09-24T17:30:00Z',
    userEmail: 'admin2@kitsune.example',
    action: 'user.login',
    resource: 'user/u-002',
    ipAddress: '10.0.0.45',
    result: 'success',
    detail: 'Login exitoso desde sesión persistente',
  },
  {
    id: 'aud-019',
    timestamp: '2026-09-24T14:10:15Z',
    userEmail: 'admin@kitsune.example',
    action: 'shipment.create',
    resource: 'shipment/SH-452',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Envío creado con tracking #NI-4568921',
  },
  {
    id: 'aud-020',
    timestamp: '2026-09-24T11:00:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'invoice.generate',
    resource: 'invoice/INV-128',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Factura generada para order #ORD-1420',
  },
  {
    id: 'aud-021',
    timestamp: '2026-09-24T09:15:30Z',
    userEmail: 'admin@kitsune.example',
    action: 'category.create',
    resource: 'category/cat-007',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Categoría "Sublimación Premium" creada',
  },
  {
    id: 'aud-022',
    timestamp: '2026-09-23T22:00:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'export.csv',
    resource: 'export/orders-2026-09',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Exportación CSV de orders de septiembre generada (1,245 filas)',
  },
  {
    id: 'aud-023',
    timestamp: '2026-09-23T18:30:45Z',
    userEmail: 'admin3@kitsune.example',
    action: 'user.login',
    resource: 'user/u-003',
    ipAddress: '10.0.0.78',
    result: 'failure',
    detail: '2FA challenge expirado (timeout 5min)',
  },
  {
    id: 'aud-024',
    timestamp: '2026-09-23T16:42:12Z',
    userEmail: 'admin@kitsune.example',
    action: 'return.approve',
    resource: 'return/RET-014',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Devolución aprobada. Monto a reembolsar: $45.00',
  },
  {
    id: 'aud-025',
    timestamp: '2026-09-23T14:20:00Z',
    userEmail: 'admin@kitsune.example',
    action: 'supplier.create',
    resource: 'supplier/sup-005',
    ipAddress: '192.168.1.10',
    result: 'success',
    detail: 'Proveedor "Textiles del Valle S.A." agregado al catálogo',
  },
] as const;
