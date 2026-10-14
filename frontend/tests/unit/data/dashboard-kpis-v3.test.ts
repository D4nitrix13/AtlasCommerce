// @vitest-environment node
import { describe, it, expect } from 'vitest';
import { buildDashboardKpisV3 } from '../../../src/data/dashboard-kpis';
import type { AccountDashboardData } from '../../../src/lib/server/account';
import type { RecentOrderMock } from '../../../src/data/account-mock';

/**
 * v3 KPI helper (Bonfire adaptation).
 *
 * Cubre:
 *   - Devuelve SIEMPRE 4 KPIs con los labelKey v3.
 *   - Empty data → "—" en órdenes/cotizaciones, valores mock en
 *     spent/favorites (esos aún no están en el payload del backend).
 *   - activeOrders cuenta pending + processing + shipped.
 *   - rfqPendingCount null → "—" en cotizaciones.
 *   - rfqPendingCount numérico → value = número.
 *   - Spent se formatea con `formatCurrency` (decimales, símbolo).
 */
describe('data/dashboard-kpis-v3', () => {
  function emptyData(): AccountDashboardData {
    return {
      user: null,
      orders: [],
      twoFactor: null,
      addresses: { list: [], total: 0, hasPrimary: false },
      rfqPendingCount: null,
      notificationsUnread: null,
      errors: {
        me: false,
        orders: false,
        twoFactor: false,
        addresses: false,
        rfq: false,
        notifications: false,
      },
    };
  }

  function makeOrder(
    overrides: Partial<RecentOrderMock> = {},
  ): RecentOrderMock {
    return {
      id: '1',
      date: '2026-10-12T00:00:00Z',
      status: 'delivered',
      total: 0,
      items: 1,
      ...overrides,
    };
  }

  it('devuelve 4 KPIs con labelKey del namespace v3', () => {
    const kpis = buildDashboardKpisV3(emptyData(), 'es');

    expect(kpis).toHaveLength(4);
    expect(kpis.map((k) => k.labelKey)).toEqual([
      'account.dashboard.v3.kpi.orders',
      'account.dashboard.v3.kpi.rfqs',
      'account.dashboard.v3.kpi.spent',
      'account.dashboard.v3.kpi.favorites',
    ]);
  });

  it('empty data → órdenes y cotizaciones con "—", spent y favorites con valores mock', () => {
    const kpis = buildDashboardKpisV3(emptyData(), 'es');

    expect(kpis[0]?.value).toBe('—'); // orders
    expect(kpis[1]?.value).toBe('—'); // rfqs
    expect(kpis[2]?.value).toContain('1,240'); // spent mock
    expect(kpis[3]?.value).toBe('12'); // favorites mock
  });

  it('cuenta active orders (pending + processing + shipped) y omite delivered/cancelled', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      orders: [
        makeOrder({ id: '1', status: 'pending' }),
        makeOrder({ id: '2', status: 'processing' }),
        makeOrder({ id: '3', status: 'shipped' }),
        makeOrder({ id: '4', status: 'delivered' }),
        makeOrder({ id: '5', status: 'cancelled' }),
      ],
    };

    const kpis = buildDashboardKpisV3(data, 'es');
    const ordersKpi = kpis[0];

    expect(ordersKpi?.value).toBe('3');
  });

  it('rfqPendingCount null → "—" en cotizaciones', () => {
    const kpis = buildDashboardKpisV3(emptyData(), 'es');

    expect(kpis[1]?.value).toBe('—');
  });

  it('rfqPendingCount numérico → value = string del número', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      rfqPendingCount: 5,
    };

    const kpis = buildDashboardKpisV3(data, 'es');
    const rfqsKpi = kpis[1];

    expect(rfqsKpi?.value).toBe('5');
    expect(rfqsKpi?.tone).toBe('warning');
  });
});
