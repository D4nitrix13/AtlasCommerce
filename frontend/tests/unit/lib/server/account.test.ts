// @vitest-environment node
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { getAccountDashboardData } from '../../../../src/lib/server/account';

/**
 * F1.1 + F2.2 — tests del helper server-side que reemplaza al mock
 * del dashboard de cuenta.
 *
 * Cubre:
 *   - Fetch en paralelo de 6 endpoints (/me, /orders, /2fa, /addresses,
 *     /rfq, /notifications).
 *   - Transform de OrderResource → RecentOrderMock.
 *   - Manejo de errores por endpoint (independiente).
 *   - Status 404 en /orders NO se considera error (admin sin Customer
 *     profile es el caso normal en este MVP).
 *   - 404 en /rfq y /notifications deja el campo null (sección oculta
 *     en el page, per spec §7).
 *   - Headers Cookie se reenvían.
 */
describe('lib/server/getAccountDashboardData (F1.1 + F2.2)', () => {
  let originalFetch: typeof fetch;

  beforeEach(() => {
    originalFetch = globalThis.fetch;
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
    vi.restoreAllMocks();
  });

  function mockResponse(
    body: unknown,
    options: { status?: number } = {},
  ): Response {
    return new Response(JSON.stringify(body), {
      status: options.status ?? 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  /**
   * Default mock factory: cada test override lo que necesita, el resto
   * responde con shape "vacío seguro" (mismo que tendría un Customer
   * nuevo sin datos). Esto evita que cada test mockee los 6 endpoints
   * a mano y mantiene legibilidad.
   */
  function defaultMock(overrides: Partial<Record<string, () => Response>> = {}): typeof fetch {
    const handlers: Record<string, () => Response> = {
      '/api/v1/auth/me': () =>
        mockResponse({
          user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] },
        }),
      '/api/v1/orders': () => mockResponse({ data: [] }),
      '/api/v1/auth/2fa/totp/status': () =>
        mockResponse({ enabled: false, enabled_at: null, unused_recovery_codes: 0 }),
      '/api/v1/account/addresses': () => mockResponse({ data: [] }),
      '/api/v1/storefront/rfq': () => mockResponse({ message: 'not found' }, { status: 404 }),
      '/api/v1/account/notifications/unread': () => mockResponse({ message: 'not found' }, { status: 404 }),
      ...overrides,
    };

    return vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      for (const [suffix, handler] of Object.entries(handlers)) {
        if (url.endsWith(suffix) || url.includes(suffix)) {
          return handler();
        }
      }
      throw new Error(`unexpected URL: ${url}`);
    }) as unknown as typeof fetch;
  }

  it('happy path — devuelve user + orders transformado del shape backend', async () => {
    const user = {
      id: 1,
      email: 'cliente@example.com',
      full_name: 'Cliente Panda',
      role: { id: 1, slug: 'admin', name: 'Admin' },
      role_id: 1,
      store_ids: [1],
      is_active: true,
      permissions: [],
    };
    const ordersResponse = {
      data: [
        {
          id: 42,
          order_number: 'ORD-001',
          status: 'delivered',
          total: { cents: 5000, formatted: 'C$ 50.00', currency_code: 'NIO' },
          ordered_at: '2026-09-30T10:00:00Z',
          items: [
            { id: 1, product_name_snapshot: 'Camisa', product_sku_snapshot: 'CAM-1', quantity: 2, unit_price_cents: 1500, subtotal_cents: 3000 },
            { id: 2, product_name_snapshot: 'Pantalón', product_sku_snapshot: 'PAN-1', quantity: 1, unit_price_cents: 2000, subtotal_cents: 2000 },
          ],
        },
        {
          id: 41,
          order_number: 'ORD-002',
          status: 'pending',
          total: { cents: 2000, formatted: 'C$ 20.00', currency_code: 'NIO' },
          ordered_at: '2026-09-28T10:00:00Z',
          items: [
            { id: 3, product_name_snapshot: 'Gorra', product_sku_snapshot: 'GOR-1', quantity: 1, unit_price_cents: 2000, subtotal_cents: 2000 },
          ],
        },
      ],
    };

    globalThis.fetch = defaultMock({
      '/api/v1/auth/me': () => mockResponse({ user }),
      '/api/v1/orders': () => mockResponse(ordersResponse),
    });

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.user).toEqual(user);
    expect(result.orders).toHaveLength(2);
    expect(result.orders[0]).toEqual({
      id: '42',
      date: '2026-09-30T10:00:00Z',
      status: 'delivered',
      total: 5000,
      items: 3,
    });
    expect(result.orders[1]).toEqual({
      id: '41',
      date: '2026-09-28T10:00:00Z',
      status: 'pending',
      total: 2000,
      items: 1,
    });
    expect(result.errors).toEqual({
      me: false,
      orders: false,
      twoFactor: false,
      addresses: false,
      rfq: false,
      notifications: false,
    });
  });

  it('orders 404 (admin sin Customer profile) → orders vacíos, errors.orders = false', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/orders': () => mockResponse({ message: 'not found' }, { status: 404 }),
    });

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.orders).toEqual([]);
    expect(result.errors.orders).toBe(false);
  });

  it('orders 500 (backend error real) → orders vacíos, errors.orders = true', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/orders': () => mockResponse({ message: 'boom' }, { status: 500 }),
    });

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.orders).toEqual([]);
    expect(result.errors.orders).toBe(true);
  });

  it('me 401 → user null + errors.me true (resto de endpoints independiente)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/auth/me': () => mockResponse({ message: 'unauthenticated' }, { status: 401 }),
    });

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.user).toBeNull();
    expect(result.errors.me).toBe(true);
    expect(result.errors.orders).toBe(false);
  });

  it('todos los endpoints rechazan (network down) → todos errors true', async () => {
    globalThis.fetch = vi.fn(async (): Promise<Response> => {
      throw new Error('network down');
    }) as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.user).toBeNull();
    expect(result.orders).toEqual([]);
    expect(result.twoFactor).toBeNull();
    expect(result.addresses.total).toBe(0);
    expect(result.rfqPendingCount).toBeNull();
    expect(result.notificationsUnread).toBeNull();
    expect(result.errors).toEqual({
      me: true,
      orders: true,
      twoFactor: true,
      addresses: true,
      rfq: true,
      notifications: true,
    });
  });

  it('cookie vacía → headers Accept pero NO Cookie en los 6 endpoints', async () => {
    const fetchMock = vi.fn(
      async (_input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
        return mockResponse({
          user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] },
          data: [],
        });
      },
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAccountDashboardData('http://localhost', '');

    expect(fetchMock).toHaveBeenCalledTimes(6);
    for (const call of fetchMock.mock.calls) {
      const init = call[1] as RequestInit | undefined;
      expect(init).toBeDefined();
      const headers = init!.headers as Record<string, string>;
      expect(headers.Cookie).toBeUndefined();
      expect(headers.Accept).toBe('application/json');
    }
  });

  it('cookie no-vacía → se reenvía en los 6 endpoints', async () => {
    const fetchMock = vi.fn(
      async (_input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
        return mockResponse({
          user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] },
          data: [],
        });
      },
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAccountDashboardData('http://localhost', 'sanctum=xyz; XSRF=abc');

    expect(fetchMock).toHaveBeenCalledTimes(6);
    for (const call of fetchMock.mock.calls) {
      const init = call[1] as RequestInit | undefined;
      expect(init).toBeDefined();
      const headers = init!.headers as Record<string, string>;
      expect(headers.Cookie).toBe('sanctum=xyz; XSRF=abc');
    }
  });

  it('status desconocido del backend → cae a "pending" (defensa)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/orders': () =>
        mockResponse({
          data: [
            {
              id: 1,
              order_number: 'ORD-001',
              status: 'quantum_entangled',
              total: { cents: 1000, formatted: 'C$ 10.00', currency_code: 'NIO' },
              ordered_at: '2026-09-30T10:00:00Z',
              items: [],
            },
          ],
        }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.orders[0]?.status).toBe('pending');
  });

  it('ordered_at null → usa fecha actual como fallback (defensa)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/orders': () =>
        mockResponse({
          data: [
            {
              id: 1,
              order_number: 'ORD-001',
              status: 'pending',
              total: { cents: 0, formatted: 'C$ 0.00', currency_code: 'NIO' },
              ordered_at: null,
              items: [],
            },
          ],
        }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.orders[0]?.date).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  });

  it('F0.x — Origin header se setea con el origin (Sanctum stateful CR2)', async () => {
    const fetchMock = vi.fn(
      async (input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
        return mockResponse({
          user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] },
          data: [],
        });
      },
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAccountDashboardData('https://panda-estampados.com', 'session=abc');

    // Cada uno de los 6 fetches debe llevar Origin = 'https://panda-estampados.com'
    // para que Sanctum stateful reconozca el request como same-origin
    // y NO rechace la cookie de sesión.
    expect(fetchMock).toHaveBeenCalledTimes(6);
    for (const call of fetchMock.mock.calls) {
      const init = call[1] as RequestInit | undefined;
      expect(init).toBeDefined();
      const headers = init!.headers as Record<string, string>;
      expect(headers.Origin).toBe('https://panda-estampados.com');
    }
  });

  it('limit se pasa como query param al endpoint /orders', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.includes('/api/v1/orders')) {
        return mockResponse({ data: [] });
      }
      return mockResponse({
        user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] },
      });
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAccountDashboardData('http://localhost', '', { limit: 7 });

    const ordersCall = fetchMock.mock.calls.find((call): boolean => {
      const url = typeof call[0] === 'string' ? call[0] : (call[0] as URL).toString();
      return url.includes('/api/v1/orders');
    });
    expect(ordersCall).toBeDefined();
    const url = typeof ordersCall![0] === 'string' ? ordersCall![0] : (ordersCall![0] as URL).toString();
    expect(url).toContain('limit=7');
  });

  it('F2.2 — twoFactor enabled → twoFactor.enabled=true, errors.twoFactor=false', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/auth/2fa/totp/status': () =>
        mockResponse({ enabled: true, enabled_at: '2026-08-15T12:00:00Z', unused_recovery_codes: 8 }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.twoFactor).toEqual({
      enabled: true,
      enabledAt: '2026-08-15T12:00:00Z',
    });
    expect(result.errors.twoFactor).toBe(false);
  });

  it('F2.2 — /2fa 401 → twoFactor=null, errors.twoFactor=true (defensa)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/auth/2fa/totp/status': () => mockResponse({ message: 'unauth' }, { status: 401 }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.twoFactor).toBeNull();
    expect(result.errors.twoFactor).toBe(true);
  });

  it('F2.2 — addresses con primary → snapshot.hasPrimary=true', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/account/addresses': () =>
        mockResponse({
          data: [
            {
              id: 1,
              customer_id: 7,
              address_type: 'shipping',
              country: 'NI',
              department: 'Managua',
              municipality: 'Managua',
              detailed_address: 'Calle 123',
              reference: null,
              is_primary: true,
              created_at: '2026-01-15T10:00:00Z',
              updated_at: '2026-01-15T10:00:00Z',
            },
            {
              id: 2,
              customer_id: 7,
              address_type: 'billing',
              country: 'NI',
              department: 'Managua',
              municipality: 'Managua',
              detailed_address: 'Calle 456',
              reference: null,
              is_primary: false,
              created_at: '2026-02-15T10:00:00Z',
              updated_at: '2026-02-15T10:00:00Z',
            },
          ],
        }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.addresses.total).toBe(2);
    expect(result.addresses.hasPrimary).toBe(true);
    expect(result.addresses.list).toHaveLength(2);
    expect(result.addresses.list[0]?.label).toBe('Managua, Managua');
  });

  it('F2.2 — addresses 404 → addresses vacías (sin Customer), errors.addresses=false', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/account/addresses': () => mockResponse({ message: 'not found' }, { status: 404 }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.addresses).toEqual({ list: [], total: 0, hasPrimary: false });
    expect(result.errors.addresses).toBe(false);
  });

  it('F2.2 — /rfq con meta.pending_count → rfqPendingCount=num', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/storefront/rfq': () =>
        mockResponse({
          data: [
            { id: 1, status: 'awaiting_approval' },
            { id: 2, status: 'pending' },
            { id: 3, status: 'approved' },
          ],
          meta: { pending_count: 5 },
        }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    // meta.pending_count gana sobre el conteo del array (autoridad backend).
    expect(result.rfqPendingCount).toBe(5);
  });

  it('F2.2 — /rfq sin meta pero con data → cuenta los pending+awaiting_approval', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/storefront/rfq': () =>
        mockResponse({
          data: [
            { id: 1, status: 'awaiting_approval' },
            { id: 2, status: 'pending' },
            { id: 3, status: 'approved' },
            { id: 4, status: 'cancelled' },
          ],
        }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.rfqPendingCount).toBe(2);
  });

  it('F2.2 — /rfq 404 → rfqPendingCount=null, errors.rfq=false (sección oculta)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/storefront/rfq': () => mockResponse({ message: 'not found' }, { status: 404 }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.rfqPendingCount).toBeNull();
    expect(result.errors.rfq).toBe(false);
  });

  it('F2.2 — /notifications 200 → notificationsUnread=num', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/account/notifications/unread': () => mockResponse({ unread_count: 7 }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.notificationsUnread).toBe(7);
  });

  it('F2.2 — /notifications 404 → notificationsUnread=null (sección oculta)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/account/notifications/unread': () => mockResponse({ message: 'not found' }, { status: 404 }),
    });

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.notificationsUnread).toBeNull();
    expect(result.errors.notifications).toBe(false);
  });
});