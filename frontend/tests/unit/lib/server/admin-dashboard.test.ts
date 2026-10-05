// @vitest-environment node
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  formatCentsAsCurrency,
  getAdminDashboardData,
} from '../../../../src/lib/server/admin-dashboard';

/**
 * F3 — tests del helper server-side del dashboard admin.
 *
 * Cubre:
 *   - Fetch en paralelo de 6 endpoints admin
 *   - Transform de cada response (yearly / sales / segments / traffic)
 *   - Manejo de errores por endpoint (independiente)
 *   - parseKpiValue: extracción de sales-performance y customer-segments
 */
describe('lib/server/getAdminDashboardData (F3)', () => {
  let originalFetch: typeof fetch;

  beforeEach(() => {
    originalFetch = globalThis.fetch;
  });

  afterEach(() => {
    globalThis.fetch = originalFetch;
    vi.restoreAllMocks();
  });

  function mockResponse(body: unknown, options: { status?: number } = {}): Response {
    return new Response(JSON.stringify(body), {
      status: options.status ?? 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  function defaultMock(
    overrides: Partial<Record<string, () => Response>> = {},
  ): typeof fetch {
    const handlers: Record<string, () => Response> = {
      '/api/v1/admin/metrics/revenue/yearly': () =>
        mockResponse({
          data: {
            year: 2026,
            previous_year: 2025,
            labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            current_year_values: [10000, 12000, 11000, 15000, 18000, 16000, 20000, 22000, 24000, 23000, 26000, 28000],
            previous_year_values: [8000, 10000, 9000, 13000, 16000, 14000, 18000, 20000, 22000, 21000, 24000, 26000],
          },
        }),
      '/api/v1/admin/metrics/sales/performance': () =>
        mockResponse({
          data: {
            metrics: [
              { label: 'Total vendido', value: '$45,000', change: '+12%', trend: 'up' },
              { label: 'Ticket promedio', value: '$125.50', change: '+3%', trend: 'up' },
              { label: 'Tasa de conversión', value: '3.2%', change: '-0.5%', trend: 'down' },
              { label: 'Devoluciones', value: '2.1%', change: '-0.3%', trend: 'down' },
            ],
            days: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            values: [0, 0, 0, 0, 0, 0, 0],
          },
        }),
      '/api/v1/admin/metrics/customers/segments': () =>
        mockResponse({
          data: {
            stats: [
              { label: 'Total clientes', value: '1,250', change: '+8%', trend: 'up' },
              { label: 'Nuevos este mes', value: '45', change: '+12%', trend: 'up' },
              { label: 'Tasa de retorno', value: '34%', change: '+2%', trend: 'up' },
              { label: 'Clientes activos', value: '847', change: '+5%', trend: 'up' },
            ],
            segments: [],
          },
        }),
      '/api/v1/admin/orders/recent': () => mockResponse({ data: [] }),
      '/api/v1/admin/metrics/traffic/sources': () =>
        mockResponse({
          data: {
            sources: [
              { source: 'google', visits: 12000, percentage: 45 },
              { source: 'direct', visits: 8000, percentage: 30 },
              { source: 'social', visits: 6666, percentage: 25 },
            ],
            top_pages: [],
          },
        }),
      '/api/v1/admin/inventory/low-stock': () => mockResponse({ data: { count: 3 } }),
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

  it('happy path — devuelve métricas reales de los 6 endpoints', async () => {
    globalThis.fetch = defaultMock();

    const result = await getAdminDashboardData('http://localhost', 'session=abc');

    // Revenue: suma de 12 meses current = 225000.
    expect(result.metrics.totalRevenueCents).toBe(225000);
    // Sales: "Total vendido" = $45,000 → 45000*100 = 4_500_000 cents.
    expect(result.metrics.totalSoldThisMonthCents).toBe(4_500_000);
    expect(result.metrics.returnsRate).toBe('2.1%');
    // Customers: "Clientes activos" = 847.
    expect(result.metrics.activeCustomers).toBe(847);
    // Traffic: suma de visits = 12000 + 8000 + 6666 = 26666.
    expect(result.metrics.totalVisits).toBe(26666);
    // Low stock.
    expect(result.metrics.lowStockCount).toBe(3);
    // Chart.
    expect(result.metrics.revenueChart?.currentValues).toHaveLength(12);
    expect(result.metrics.revenueChart?.year).toBe(2026);
    expect(result.errors).toEqual({
      metrics: false,
      recentOrders: false,
      trafficSources: false,
    });
  });

  it('revenue/yearly 500 → totalRevenueCents=null, errors.metrics=false (otros endpoints siguen OK)', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/admin/metrics/revenue/yearly': () => mockResponse({ message: 'boom' }, { status: 500 }),
    });

    const result = await getAdminDashboardData('http://localhost', '');

    expect(result.metrics.totalRevenueCents).toBeNull();
    expect(result.metrics.revenueChart).toBeNull();
    // errors.metrics solo es true si revenue + sales + segments fallan TODOS.
    expect(result.errors.metrics).toBe(false);
  });

  it('todos los endpoints de metrics fallan → errors.metrics=true', async () => {
    globalThis.fetch = vi.fn(async (): Promise<Response> => {
      throw new Error('network down');
    }) as unknown as typeof fetch;

    const result = await getAdminDashboardData('http://localhost', '');

    expect(result.metrics.totalRevenueCents).toBeNull();
    expect(result.metrics.activeCustomers).toBeNull();
    expect(result.metrics.totalSoldThisMonthCents).toBeNull();
    expect(result.metrics.totalVisits).toBeNull();
    expect(result.errors.metrics).toBe(true);
  });

  it('orders/recent 500 → recentOrders=[], errors.recentOrders=true', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/admin/orders/recent': () => mockResponse({ message: 'boom' }, { status: 500 }),
    });

    const result = await getAdminDashboardData('http://localhost', '');

    expect(result.recentOrders).toEqual([]);
    expect(result.errors.recentOrders).toBe(true);
  });

  it('traffic/sources 200 con sources vacías → totalVisits=0, trafficSources=[]', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/admin/metrics/traffic/sources': () =>
        mockResponse({ data: { sources: [], top_pages: [] } }),
    });

    const result = await getAdminDashboardData('http://localhost', '');

    expect(result.metrics.totalVisits).toBe(0);
    expect(result.trafficSources).toEqual([]);
  });

  it('cookie header se reenvía en los 6 fetches', async () => {
    const fetchMock = vi.fn(
      async (input: RequestInfo | URL, _init?: RequestInit): Promise<Response> => {
        const url = typeof input === 'string' ? input : input.toString();
        // Cada endpoint tiene su shape; devolvemos un shape compatible
        // con cada uno (los pages toleran shape incorrecto con fallback).
        if (url.includes('/orders/recent')) {
          return mockResponse({ data: [] });
        }
        if (url.includes('/traffic/sources')) {
          return mockResponse({ data: { sources: [], top_pages: [] } });
        }
        if (url.includes('/low-stock')) {
          return mockResponse({ data: { count: 0 } });
        }
        if (url.includes('/customers/segments')) {
          return mockResponse({ data: { stats: [], segments: [] } });
        }
        if (url.includes('/sales/performance')) {
          return mockResponse({ data: { metrics: [], days: [], values: [] } });
        }
        // revenue/yearly u otros.
        return mockResponse({
          data: { year: 2026, previous_year: 2025, labels: [], current_year_values: [], previous_year_values: [] },
        });
      },
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    await getAdminDashboardData('http://localhost', 'sanctum=xyz');

    expect(fetchMock).toHaveBeenCalledTimes(6);
    for (const call of fetchMock.mock.calls) {
      const init = call[1] as RequestInit | undefined;
      const headers = init?.headers as Record<string, string> | undefined;
      expect(headers?.Cookie).toBe('sanctum=xyz');
      expect(headers?.Accept).toBe('application/json');
    }
  });

  it('parseKpiValue defensivo — value "—" o "" devuelve null', async () => {
    globalThis.fetch = defaultMock({
      '/api/v1/admin/metrics/sales/performance': () =>
        mockResponse({
          data: {
            metrics: [
              { label: 'Total vendido', value: '—', change: '0%', trend: 'up' },
              { label: 'Ticket promedio', value: '', change: '0%', trend: 'up' },
              { label: 'Tasa de conversión', value: '3.2%', change: '0%', trend: 'up' },
              { label: 'Devoluciones', value: '2.1%', change: '0%', trend: 'up' },
            ],
            days: [],
            values: [],
          },
        }),
      '/api/v1/admin/metrics/customers/segments': () =>
        mockResponse({
          data: {
            stats: [
              { label: 'Total clientes', value: '0', change: '0%', trend: 'up' },
              { label: 'Nuevos este mes', value: '0', change: '0%', trend: 'up' },
              { label: 'Tasa de retorno', value: '0%', change: '0%', trend: 'up' },
              { label: 'Clientes activos', value: '—', change: '0%', trend: 'up' },
            ],
            segments: [],
          },
        }),
    });

    const result = await getAdminDashboardData('http://localhost', '');

    expect(result.metrics.totalSoldThisMonthCents).toBeNull();
    expect(result.metrics.activeCustomers).toBeNull();
  });
});

describe('lib/server/formatCentsAsCurrency (F3 helper)', () => {
  it('formatea cents a USD con locale en', () => {
    // 12345 cents = $123.45, sin decimales = "$123".
    const out = formatCentsAsCurrency(12345, 'USD', 'en');
    expect(out).toContain('$');
    expect(out).toContain('123');
  });

  it('formatea cents a NIO con locale es', () => {
    const out = formatCentsAsCurrency(50000, 'NIO', 'es');
    expect(out).toContain('500');
  });

  it('default locale=en, default currency=USD', () => {
    const out = formatCentsAsCurrency(10000);
    expect(out).toContain('$');
  });
});