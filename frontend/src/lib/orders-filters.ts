/**
 * Job-ticket ledger query model — `/[locale]/account/orders`.
 *
 * Espeja el patrón SSR de `lib/catalog-filters.ts` (PLP): el estado del
 * listado vive en la query string, los filtros son links GET y la página
 * filtra en el servidor. Sin islas React, sin estado en cliente.
 *
 * Params tolerantes: todo valor inválido se ignora (fallback al default)
 * en vez de romper el listado.
 *
 * `shown` controla cuántas filas renderiza el ledger ("Cargar más
 * órdenes"). Cambiar status u orden lo resetea al default — misma
 * convención que `buildCatalogHref` con `page`.
 */

export const ORDER_STATUS_FILTER_VALUES = [
  'all',
  'pending',
  'processing',
  'shipped',
  'delivered',
  'cancelled',
] as const;

export type OrderStatusFilterValue = (typeof ORDER_STATUS_FILTER_VALUES)[number];

export const ORDERS_SORT_VALUES = ['recent', 'oldest'] as const;
export type OrdersSortValue = (typeof ORDERS_SORT_VALUES)[number];

export const DEFAULT_ORDERS_SORT: OrdersSortValue = 'recent';
export const DEFAULT_ORDERS_SHOWN = 6;

/** Estado del listado, ya normalizado y listo para aplicar. */
export interface OrdersQuery {
  status: OrderStatusFilterValue;
  sort: OrdersSortValue;
  /** Cuántas filas renderizar (más recientes/antiguas según sort). */
  shown: number;
}

/** Campos que `applyOrdersQuery` necesita de cada orden. */
export interface OrderQueryable {
  status: string;
  /** ISO 8601 — fecha del pedido. */
  date: string;
}

const STATUS_SET: ReadonlySet<string> = new Set<string>(ORDER_STATUS_FILTER_VALUES);
const SORT_SET: ReadonlySet<string> = new Set<string>(ORDERS_SORT_VALUES);

function parseShownParam(raw: string | null): number {
  if (raw === null) return DEFAULT_ORDERS_SHOWN;
  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : DEFAULT_ORDERS_SHOWN;
}

/**
 * Normaliza la query string del ledger. Tolera status/sort desconocidos
 * y `shown` no numérico: cada param malformado cae al default.
 */
export function parseOrdersQuery(searchParams: URLSearchParams): OrdersQuery {
  const status = searchParams.get('status')?.trim() ?? '';
  const sort = searchParams.get('sort')?.trim() ?? '';

  return {
    status: STATUS_SET.has(status) ? (status as OrderStatusFilterValue) : 'all',
    sort: SORT_SET.has(sort) ? (sort as OrdersSortValue) : DEFAULT_ORDERS_SORT,
    shown: parseShownParam(searchParams.get('shown')),
  };
}

/**
 * Filtra por status, ordena por fecha y recorta a `shown`.
 *
 * No pagina: el recorte vive en el render (`shown`) para que los
 * conteos de los pills se calculen sobre el conjunto completo.
 */
export function applyOrdersQuery<T extends OrderQueryable>(
  orders: readonly T[],
  query: OrdersQuery
): T[] {
  const filtered =
    query.status === 'all' ? [...orders] : orders.filter((order) => order.status === query.status);

  const sorted = filtered.sort((a, b) =>
    query.sort === 'oldest'
      ? Date.parse(a.date) - Date.parse(b.date)
      : Date.parse(b.date) - Date.parse(a.date)
  );

  return sorted.slice(0, query.shown);
}

/**
 * Arma el href de un control del ledger: `current` con el `patch`
 * encima, sin params default (URL limpia).
 *
 * Cambiar status u orden resetea `shown` al default (volver a la
 * primera tanda) salvo que el patch traiga su propio `shown`.
 */
export function buildOrdersHref(current: OrdersQuery, patch: Partial<OrdersQuery>): string {
  const changesFilter = patch.status !== undefined || patch.sort !== undefined;
  const next: OrdersQuery = {
    status: patch.status ?? current.status,
    sort: patch.sort ?? current.sort,
    shown: patch.shown ?? (changesFilter ? DEFAULT_ORDERS_SHOWN : current.shown),
  };

  const params = new URLSearchParams();
  if (next.status !== 'all') params.set('status', next.status);
  if (next.sort !== DEFAULT_ORDERS_SORT) params.set('sort', next.sort);
  if (next.shown > DEFAULT_ORDERS_SHOWN) params.set('shown', String(next.shown));

  const queryString = params.toString();
  return queryString === '' ? '' : `?${queryString}`;
}

/** Conteos por status para los pills de filtro. */
export function countOrdersByStatus<T extends { status: string }>(
  orders: readonly T[]
): Record<OrderStatusFilterValue, number> {
  const counts = {
    all: orders.length,
    pending: 0,
    processing: 0,
    shipped: 0,
    delivered: 0,
    cancelled: 0,
  } satisfies Record<OrderStatusFilterValue, number>;

  for (const order of orders) {
    if (STATUS_SET.has(order.status) && order.status !== 'all') {
      counts[order.status as OrderStatusFilterValue] += 1;
    }
  }

  return counts;
}
