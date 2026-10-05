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
 * F2.2 — Extendido con datos para las 8 secciones del dashboard
 * rediseñado (ver `docs/frontend/account-dashboard-spec.md`):
 *   - 2FA status (TOTP enabled)         → SecuritySnapshot
 *   - Direcciones del Customer           → AddressesSnapshot
 *   - Cotizaciones pendientes (RFQ)      → RfqSnapshot
 *   - Notificaciones sin leer            → NotificationsSnapshot
 *   - User + orders (existente F1.1)     → ProfileSummary + OrderStatusCard×3
 *
 * Endpoints consumidos:
 *   - GET /api/v1/auth/me                  → User
 *   - GET /api/v1/orders?limit=N          → RecentOrderMock[]
 *   - GET /api/v1/auth/2fa/totp/status     → { enabled, enabled_at }
 *   - GET /api/v1/account/addresses        → CustomerAddress[]
 *   - GET /api/v1/storefront/rfq           → RFQ list (PENDIENTE — endpoint
 *     solo existe como POST. Cuando se cree, el helper lo enchufa
 *     sin tocar la API del caller.)
 *   - GET /api/v1/account/notifications    → notif count (PENDIENTE —
 *     mismo caso: el helper ya hace el fetch, devuelve null si 404
 *     y la sección queda oculta per spec §7.)
 *
 * Multi-tenant: el middleware backend ya resolvió la tienda activa
 * vía `store.domain` antes de llegar a estas rutas. El locale del
 * storefront viene del middleware Astro (`Astro.locals.locale`).
 *
 * Failure modes (por sección, independientes — un 5xx en /me no
 * rompe /orders):
 *   - 401/500 en /me: user=null, errors.me=true. La página usa
 *     fallback "Cliente" para el nombre.
 *   - 404 en /orders: orders=[], errors.orders=false. Caso normal
 *     para admin/staff sin Customer profile — no es error de UX.
 *   - 4xx/5xx en /orders: orders=[], errors.orders=true.
 *   - 404 en /addresses (no Customer): addresses=[], errors.addresses=false.
 *   - Cualquier status en /2fa, /rfq, /notifications no disponibles:
 *     el campo queda null y la sección se oculta per spec.
 */
export interface AccountDashboardData {
  user: User | null;
  orders: RecentOrderMock[];
  twoFactor: TwoFactorSnapshot | null;
  addresses: AddressSnapshot;
  rfqPendingCount: number | null;
  notificationsUnread: number | null;
  errors: {
    me: boolean;
    orders: boolean;
    twoFactor: boolean;
    addresses: boolean;
    rfq: boolean;
    notifications: boolean;
  };
}

export interface TwoFactorSnapshot {
  enabled: boolean;
  enabledAt: string | null;
}

export interface AddressSnapshotItem {
  id: number;
  addressType: 'shipping' | 'billing' | 'other' | string;
  label: string;
  isPrimary: boolean;
}

export interface AddressSnapshot {
  list: AddressSnapshotItem[];
  total: number;
  hasPrimary: boolean;
}

interface BackendUserMeResponse {
  user: User;
}

interface BackendOrderListResponse {
  data: BackendOrder[];
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

interface BackendTwoFactorResponse {
  enabled: boolean;
  enabled_at: string | null;
  unused_recovery_codes: number;
}

interface BackendAddressResource {
  id: number;
  customer_id: number;
  address_type: string;
  country: string | null;
  department: string | null;
  municipality: string | null;
  detailed_address: string | null;
  reference: string | null;
  is_primary: boolean;
  created_at: string | null;
  updated_at: string | null;
}

interface BackendAddressListResponse {
  data: BackendAddressResource[];
}

interface BackendRfqListResponse {
  data: BackendRfqResource[];
  meta?: {
    pending_count?: number;
  };
}

interface BackendRfqResource {
  id: number;
  status: string;
  [key: string]: unknown;
}

interface BackendNotificationCountResponse {
  unread_count: number;
}

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

function toAddressSnapshotItem(addr: BackendAddressResource): AddressSnapshotItem {
  const city = addr.municipality ?? '';
  const dept = addr.department ?? '';
  const label = [city, dept].filter((s) => s !== '').join(', ');

  return {
    id: addr.id,
    addressType: addr.address_type,
    label: label !== '' ? label : (addr.country ?? ''),
    isPrimary: addr.is_primary,
  };
}

/**
 * Helper principal. Llama al backend en paralelo y devuelve los
 * datos del dashboard con errors por endpoint.
 *
 * Si `limit` no se pasa, default 3 (suficiente para la sección hero
 * de pedidos que muestra 3 cards).
 */
export async function getAccountDashboardData(
  origin: string,
  cookie: string,
  options: { limit?: number } = {},
): Promise<AccountDashboardData> {
  const limit = options.limit ?? 3;
  const headers: HeadersInit = {
    Accept: 'application/json',
  };
  if (cookie !== '') {
    headers.Cookie = cookie;
  }

  const fetchOptions: RequestInit = { headers };

  const [
    meResult,
    ordersResult,
    twoFactorResult,
    addressesResult,
    rfqResult,
    notificationsResult,
  ] = await Promise.allSettled([
    fetch(new URL('/api/v1/auth/me', origin), fetchOptions),
    fetch(new URL(`/api/v1/orders?limit=${String(limit)}`, origin), fetchOptions),
    fetch(new URL('/api/v1/auth/2fa/totp/status', origin), fetchOptions),
    fetch(new URL('/api/v1/account/addresses', origin), fetchOptions),
    fetch(new URL('/api/v1/storefront/rfq', origin), fetchOptions),
    fetch(new URL('/api/v1/account/notifications/unread', origin), fetchOptions),
  ]);

  let user: User | null = null;
  let meError = false;
  if (meResult.status === 'fulfilled') {
    if (meResult.value.ok) {
      const body = (await meResult.value.json()) as BackendUserMeResponse;
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
    if (ordersResult.value.ok) {
      const body = (await ordersResult.value.json()) as BackendOrderListResponse;
      orders = (body.data ?? []).map(toRecentOrderMock);
    } else if (ordersResult.value.status !== 404) {
      ordersError = true;
    }
  } else {
    ordersError = true;
  }

  let twoFactor: TwoFactorSnapshot | null = null;
  let twoFactorError = false;
  if (twoFactorResult.status === 'fulfilled') {
    if (twoFactorResult.value.ok) {
      const body = (await twoFactorResult.value.json()) as BackendTwoFactorResponse;
      twoFactor = {
        enabled: body.enabled,
        enabledAt: body.enabled_at,
      };
    } else {
      twoFactorError = true;
    }
  } else {
    twoFactorError = true;
  }

  let addresses: AddressSnapshot = { list: [], total: 0, hasPrimary: false };
  let addressesError = false;
  if (addressesResult.status === 'fulfilled') {
    if (addressesResult.value.ok) {
      const body = (await addressesResult.value.json()) as BackendAddressListResponse;
      const list = (body.data ?? []).map(toAddressSnapshotItem);
      addresses = {
        list,
        total: list.length,
        hasPrimary: list.some((a) => a.isPrimary),
      };
    } else if (addressesResult.value.status !== 404) {
      addressesError = true;
    }
  } else {
    addressesError = true;
  }

  let rfqPendingCount: number | null = null;
  let rfqError = false;
  if (rfqResult.status === 'fulfilled') {
    if (rfqResult.value.ok) {
      const body = (await rfqResult.value.json()) as BackendRfqListResponse;
      // El backend puede exponer `meta.pending_count` o contar a mano.
      const metaCount = body.meta?.pending_count;
      if (typeof metaCount === 'number') {
        rfqPendingCount = metaCount;
      } else if (Array.isArray(body.data)) {
        rfqPendingCount = body.data.filter(
          (r: BackendRfqResource): boolean => r.status === 'pending' || r.status === 'awaiting_approval',
        ).length;
      } else {
        rfqPendingCount = 0;
      }
    } else if (rfqResult.value.status === 404) {
      // Endpoint no existe — rfqPendingCount queda null, sección se oculta.
      rfqPendingCount = null;
    } else {
      rfqError = true;
    }
  } else {
    rfqError = true;
  }

  let notificationsUnread: number | null = null;
  let notificationsError = false;
  if (notificationsResult.status === 'fulfilled') {
    if (notificationsResult.value.ok) {
      const body = (await notificationsResult.value.json()) as BackendNotificationCountResponse;
      notificationsUnread = body.unread_count;
    } else if (notificationsResult.value.status === 404) {
      // Endpoint no existe — null, sección oculta.
      notificationsUnread = null;
    } else {
      notificationsError = true;
    }
  } else {
    notificationsError = true;
  }

  return {
    user,
    orders,
    twoFactor,
    addresses,
    rfqPendingCount,
    notificationsUnread,
    errors: {
      me: meError,
      orders: ordersError,
      twoFactor: twoFactorError,
      addresses: addressesError,
      rfq: rfqError,
      notifications: notificationsError,
    },
  };
}