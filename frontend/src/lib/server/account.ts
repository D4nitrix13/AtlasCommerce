import type { User } from '../../types/auth';
import type { OrderStatus, RecentOrderMock } from '../../data/account-mock';

/**
 * Server-side helper para el dashboard de cuenta (`/account`).
 *
 * F1.1 — Reemplaza el mock `CURRENT_USER`/`getRecentOrders()` por
 * fetch real al backend Laravel con la cookie httpOnly de Sanctum
 * stateful (CR2). Encapsula la lógica de fetch + transform + error
 * handling para que la página .astro se quede con solo presentación.
 *
 * Endpoints consumidos:
 *   - GET /api/v1/auth/me       → User actual (full_name, role, etc.)
 *   - GET /api/v1/orders        → órdenes del cliente autenticado.
 *     El backend resuelve el Customer desde `users.id` (RF-CUST-040);
 *     si el user NO tiene Customer asociado devuelve 404.
 *
 * Multi-tenant: el middleware backend ya resolvió la tienda activa
 * vía `store.domain` antes de llegar a estas rutas. El locale del
 * storefront viene del middleware Astro (`Astro.locals.locale`).
 *
 * Response shape:
 *   {
 *     user: User | null,                // null si /me falló
 *     orders: RecentOrderMock[],        // [] si /orders falló O vacío
 *     errors: { me?: true, orders?: true },
 *   }
 *
 * Failure modes:
 *   - /me falla (401, 500): la página debe redirigir al login (o
 *     mostrar fallback). El caller decide — `getAccountDashboardData`
 *     no redirige.
 *   - /orders 404 (no Customer profile): orders=[], errors.orders=undefined
 *     (404 en /orders es esperado para admin/staff sin perfil de cliente,
 *     no es un error de UX — se trata como "no hay órdenes").
 *   - /orders 500/502/etc: orders=[], errors.orders=true (mostrar CTA retry).
 */
export interface AccountDashboardData {
  user: User | null;
  orders: RecentOrderMock[];
  errors: {
    me: boolean;
    orders: boolean;
  };
}

interface BackendUserMeResponse {
  user: User;
}

interface BackendOrderListResponse {
  data: BackendOrder[];
  // Cursor pagination metadata (next_cursor, prev_cursor, etc.) — ignorado
  // por el dashboard que solo muestra los últimos 5.
}

interface BackendOrder {
  id: number;
  order_number: string;
  status: string;
  total: {
    cents: number;
    formatted: string;
    currency_code: string | null;
  };
  ordered_at: string | null;
  items: BackendOrderItem[];
}

interface BackendOrderItem {
  id: number;
  product_name_snapshot: string;
  product_sku_snapshot: string;
  quantity: number;
  unit_price_cents: number;
  subtotal_cents: number;
}

/**
 * Mapea el status string del backend (`pending`, `processing`, etc.)
 * al union de `OrderStatus` del componente.
 *
 * Defensivo: si el backend agrega un status nuevo que el componente
 * no conoce, devolvemos 'pending' como fallback visible (NO Illegal.
 *
 * Acceptamos las variants comunes del backend; cualquier valor
 * fuera del union cae a 'pending' (gris en el Badge).
 */
function normalizeOrderStatus(rawStatus: string): OrderStatus {
  const known: readonly OrderStatus[] = [
    'pending',
    'processing',
    'shipped',
    'delivered',
    'cancelled',
  ];
  if ((known as readonly string[]).includes(rawStatus)) {
    return rawStatus as OrderStatus;
  }
  return 'pending';
}

/**
 * Convierte un `BackendOrder` al shape `RecentOrderMock` que consume
 * el componente `RecentOrdersList.astro`. Mantiene compatibilidad
 * con los field names actuales (`id`, `date`, `status`, `total`, `items`)
 * para no tocar el componente en este PR.
 */
function toRecentOrderMock(order: BackendOrder): RecentOrderMock {
  const itemsCount = order.items.reduce(
    (sum: number, item: BackendOrderItem): number => sum + item.quantity,
    0,
  );

  return {
    id: String(order.id),
    date: order.ordered_at ?? new Date().toISOString(),
    status: normalizeOrderStatus(order.status),
    total: order.total.cents,
    items: itemsCount,
  };
}

/**
 * Helper principal. Llama al backend en paralelo y devuelve los
 * datos del dashboard con errors por endpoint.
 *
 * @param origin   Base URL del backend (típicamente `Astro.url.origin`).
 * @param cookie   Cookie header del request entrante (Sanctum stateful).
 * @param options.limit  Cantidad máxima de órdenes a devolver (default 5).
 */
export async function getAccountDashboardData(
  origin: string,
  cookie: string,
  options: { limit?: number } = {},
): Promise<AccountDashboardData> {
  const limit = options.limit ?? 5;
  const headers: HeadersInit = {
    Accept: 'application/json',
  };
  if (cookie !== '') {
    headers.Cookie = cookie;
  }

  // Fetch en paralelo — ambos endpoints son independientes. Si uno
  // falla, el otro sigue su flujo.
  const [meResult, ordersResult] = await Promise.allSettled([
    fetch(new URL('/api/v1/auth/me', origin), { headers }),
    fetch(new URL(`/api/v1/orders?limit=${String(limit)}`, origin), { headers }),
  ]);

  let user: User | null = null;
  let meError = false;

  if (meResult.status === 'fulfilled') {
    const response = meResult.value;
    if (response.ok) {
      const body = (await response.json()) as BackendUserMeResponse;
      user = body.user;
    } else {
      meError = true;
    }
  } else {
    meError = true;
  }

  let orders: RecentOrderMock[] = [];
  let ordersError = false;

  if (ordersResult.status === 'fulfilled') {
    const response = ordersResult.value;
    if (response.ok) {
      const body = (await response.json()) as BackendOrderListResponse;
      orders = (body.data ?? []).map(toRecentOrderMock);
    } else if (response.status !== 404) {
      // 404 = "user sin Customer profile" → ordenes vacías, NO error.
      // 4xx/5xx = error real de backend.
      ordersError = true;
    }
  } else {
    ordersError = true;
  }

  return {
    user,
    orders,
    errors: {
      me: meError,
      orders: ordersError,
    },
  };
}