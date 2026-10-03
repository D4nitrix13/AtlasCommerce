/**
 * Mock data temporal para el dashboard de cuenta (F1.3).
 *
 * En esta fase NO hay endpoints reales para `GET /api/storefront/me`
 * ni `GET /api/storefront/orders`. Cuando existan, este archivo se
 * elimina y se reemplaza por fetch + React island.
 *
 * Convenciones que vienen del backend:
 *   - `total` se expresa en CENTAVOS (entero). Lo que ve el usuario
 *     se formatea con `formatCentsAsDecimal` de `lib/formatters.ts`.
 *   - `status` matchea exactamente las variants del Badge primitive
 *     (`pending | processing | shipped | delivered | cancelled`).
 *   - `storeSlug` viene del tenant resuelto por middleware. Se usa
 *     para decidir el saludo del banner (Kitsune vs Panda).
 *
 * **SOLO para vista**. NO tomar decisiones de negocio con esto.
 */

export type OrderStatus = 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';

export type UserRole = 'customer' | 'admin';

export type StoreSlug = 'kitsune' | 'panda-estampados' | 'mm-boutique' | 'morales-store';

export interface CurrentUserMock {
  firstName: string;
  email: string;
  locale: 'es' | 'en';
  storeSlug: StoreSlug;
  role: UserRole;
}

export interface RecentOrderMock {
  id: string;
  date: string; // ISO 8601
  status: OrderStatus;
  total: number; // cents
  items: number;
}

/**
 * Usuario autenticado simulado.
 *
 * El `storeSlug` define el saludo del banner de bienvenida:
 *   - kitsune → "おかえりなさい, {name}"
 *   - panda-estampados (o cualquier no-kitsune) → "Bienvenido de vuelta, {name}"
 */
export const CURRENT_USER: CurrentUserMock = {
  firstName: 'Daniel Benjamin',
  email: 'daniel@example.com',
  locale: 'es',
  storeSlug: 'kitsune',
  role: 'customer',
};

const MAX_RECENT_ORDERS = 5;

/**
 * Últimos 5 pedidos. Datos estables para que la vista no cambie entre
 * renders (los IDs y fechas son del 2026-09 hacia atrás).
 */
export const RECENT_ORDERS_MOCK: RecentOrderMock[] = [
  { id: '12345', date: '2026-09-25', status: 'shipped', total: 4500, items: 3 },
  { id: '12340', date: '2026-09-18', status: 'delivered', total: 12000, items: 7 },
  { id: '12338', date: '2026-09-10', status: 'delivered', total: 3400, items: 2 },
  { id: '12335', date: '2026-08-30', status: 'cancelled', total: 0, items: 1 },
  { id: '12330', date: '2026-08-22', status: 'delivered', total: 8600, items: 4 },
];

/**
 * Devuelve los últimos N pedidos (default `MAX_RECENT_ORDERS`).
 *
 * Cuando llegue el endpoint real, esta función se reemplaza por
 * `fetch('/api/storefront/orders?limit=5')`.
 */
export function getRecentOrders(limit: number = MAX_RECENT_ORDERS): RecentOrderMock[] {
  return RECENT_ORDERS_MOCK.slice(0, limit);
}
