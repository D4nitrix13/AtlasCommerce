// @vitest-environment node
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { getAccountDashboardData } from '../../../../src/lib/server/account';

/**
 * F1.1 — tests del helper server-side que reemplaza al mock del
 * dashboard de cuenta. Cubre:
 *   - Fetch en paralelo de /me y /orders
 *   - Transform de OrderResource → RecentOrderMock
 *   - Manejo de errores por endpoint (independiente)
 *   - Status 404 en /orders NO se considera error (admin sin Customer
 *     profile es el caso normal en este MVP)
 *   - Headers Cookie se reenvían
 */
describe('lib/server/getAccountDashboardData (F1.1)', () => {
  let originalFetch: typeof fetch;

  beforeEach(() => {
    originalFetch = globalThis.fetch;
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
    vi.restoreAllMocks();
  });

  function mockFetchResponse(
    url: string,
    body: unknown,
    options: { status?: number; ok?: boolean } = {},
  ): Response {
    return new Response(JSON.stringify(body), {
      status: options.status ?? 200,
      headers: { 'Content-Type': 'application/json' },
    });
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

    const fetchMock = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { user });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, ordersResponse);
      }
      throw new Error(`unexpected URL: ${url}`);
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.user).toEqual(user);
    expect(result.orders).toHaveLength(2);
    expect(result.orders[0]).toEqual({
      id: '42',
      date: '2026-09-30T10:00:00Z',
      status: 'delivered',
      total: 5000,
      items: 3, // quantity sum
    });
    expect(result.orders[1]).toEqual({
      id: '41',
      date: '2026-09-28T10:00:00Z',
      status: 'pending',
      total: 2000,
      items: 1,
    });
    expect(result.errors).toEqual({ me: false, orders: false });
  });

  it('orders 404 (admin sin Customer profile) → orders vacíos, errors.orders = false', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] } });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, { message: 'not found' }, { status: 404 });
      }
      throw new Error(`unexpected URL: ${url}`);
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.orders).toEqual([]);
    expect(result.errors.orders).toBe(false);
  });

  it('orders 500 (backend error real) → orders vacíos, errors.orders = true', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] } });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, { message: 'boom' }, { status: 500 });
      }
      throw new Error(`unexpected URL: ${url}`);
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.orders).toEqual([]);
    expect(result.errors.orders).toBe(true);
  });

  it('me 401 → user null + errors.me true (orders independiente)', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { message: 'unauthenticated' }, { status: 401 });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, { data: [] });
      }
      throw new Error(`unexpected URL: ${url}`);
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.user).toBeNull();
    expect(result.errors.me).toBe(true);
    expect(result.errors.orders).toBe(false);
  });

  it('ambos endpoints rechazan (network down) → ambos errors true', async () => {
    globalThis.fetch = vi.fn(async (): Promise<Response> => {
      throw new Error('network down');
    }) as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', 'session=abc');

    expect(result.user).toBeNull();
    expect(result.orders).toEqual([]);
    expect(result.errors).toEqual({ me: true, orders: true });
  });

  it('cookie vacía → headers Accept pero NO Cookie', async () => {
    const fetchMock = vi.fn(
      async (_input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
        return mockFetchResponse('', { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] }, data: [] });
      },
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAccountDashboardData('http://localhost', '');

    // Ambos fetch calls (me + orders) deben NO haber recibido Cookie header.
    expect(fetchMock).toHaveBeenCalledTimes(2);
    for (const call of fetchMock.mock.calls) {
      const init = call[1] as RequestInit | undefined;
      expect(init).toBeDefined();
      const headers = init!.headers as Record<string, string>;
      expect(headers.Cookie).toBeUndefined();
      expect(headers.Accept).toBe('application/json');
    }
  });

  it('cookie no-vacía → se reenvía en ambos endpoints', async () => {
    const fetchMock = vi.fn(
      async (_input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
        return mockFetchResponse('', { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] }, data: [] });
      },
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAccountDashboardData('http://localhost', 'sanctum=xyz; XSRF=abc');

    for (const call of fetchMock.mock.calls) {
      const init = call[1] as RequestInit | undefined;
      expect(init).toBeDefined();
      const headers = init!.headers as Record<string, string>;
      expect(headers.Cookie).toBe('sanctum=xyz; XSRF=abc');
    }
  });

  it('status desconocido del backend → cae a "pending" (defensa)', async () => {
    const ordersResponse = {
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
    };

    globalThis.fetch = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] } });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, ordersResponse);
      }
      throw new Error(`unexpected URL: ${url}`);
    }) as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.orders[0]?.status).toBe('pending');
  });

  it('ordered_at null → usa fecha actual como fallback (defensa)', async () => {
    const ordersResponse = {
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
    };

    globalThis.fetch = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] } });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, ordersResponse);
      }
      throw new Error(`unexpected URL: ${url}`);
    }) as unknown as typeof fetch;

    const result = await getAccountDashboardData('http://localhost', '');

    expect(result.orders[0]?.date).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  });

  it('limit se pasa como query param al endpoint /orders', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = typeof input === 'string' ? input : input.toString();
      if (url.endsWith('/api/v1/auth/me')) {
        return mockFetchResponse(url, { user: { id: 1, email: 'a@a.com', full_name: 'A', role: null, role_id: null, store_ids: [], is_active: true, permissions: [] } });
      }
      if (url.includes('/api/v1/orders')) {
        return mockFetchResponse(url, { data: [] });
      }
      throw new Error(`unexpected URL: ${url}`);
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
});