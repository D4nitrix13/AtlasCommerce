// @vitest-environment node
import { describe, it, expect } from 'vitest';
import { buildDashboardKpis } from '../../../src/data/dashboard-kpis';
import type { AccountDashboardData } from '../../../src/lib/server/account';
import type { RecentOrderMock } from '../../../src/data/account-mock';

/**
 * F5.5 — Helper que produce las 4 KPIs del dashboard desde los datos
 * que ya consume `lib/server/account.getAccountDashboardData`.
 *
 * Cubre:
 *   - Devuelve SIEMPRE 4 KPIs (mismo set que el mockup).
 *   - Empty data → todas con value `"—"`, sin delta/hint.
 *   - rfqPendingCount null → "—" en KPI 3.
 *   - Suma de pending balance (ordenes en processing/pending).
 *   - Tonos correctos por tipo (orders positive, pending warning).
 */
describe('data/dashboard-kpis (F5.5)', () => {
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

  it('devuelve siempre 4 KPIs aunque todos los datos estén vacíos', () => {
    const kpis = buildDashboardKpis(emptyData(), 'es');

    expect(kpis).toHaveLength(4);
    expect(kpis.map((k) => k.labelKey)).toEqual([
      'account.dashboard.kpi.ordersTotal',
      'account.dashboard.kpi.processing',
      'account.dashboard.kpi.rfqApproval',
      'account.dashboard.kpi.pendingBalance',
    ]);
  });

  it('empty data → todas las KPIs con value "—" y sin delta/hint', () => {
    const kpis = buildDashboardKpis(emptyData(), 'es');

    for (const k of kpis) {
      expect(k.value).toBe('—');
      expect(k.delta).toBeNull();
      expect(k.hint).toBeNull();
    }
  });

  it('orders.length = 3 → "3" en ordersTotal KPI', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      orders: [makeOrder(), makeOrder(), makeOrder()],
    };

    const kpis = buildDashboardKpis(data, 'es');
    const ordersKpi = kpis[0];

    expect(ordersKpi?.value).toBe('3');
  });

  it('processing + pending orders se cuentan en processingKpi', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      orders: [
        makeOrder({ id: '1', status: 'processing' }),
        makeOrder({ id: '2', status: 'pending' }),
        makeOrder({ id: '3', status: 'delivered' }),
      ],
    };

    const kpis = buildDashboardKpis(data, 'es');
    const processingKpi = kpis[1];

    expect(processingKpi?.value).toBe('2');
  });

  it('rfqPendingCount null → "—" en rfqApproval KPI', () => {
    const kpis = buildDashboardKpis(emptyData(), 'es');
    const rfqKpi = kpis[2];

    expect(rfqKpi?.value).toBe('—');
    expect(rfqKpi?.labelKey).toBe('account.dashboard.kpi.rfqApproval');
  });

  it('rfqPendingCount = 87 → value "87%" en rfqApproval KPI', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      rfqPendingCount: 87,
    };

    const kpis = buildDashboardKpis(data, 'es');
    const rfqKpi = kpis[2];

    expect(rfqKpi?.value).toBe('87%');
  });

  it('pendingKpi suma los totales de órdenes en processing/pending', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      orders: [
        makeOrder({ id: '1', status: 'processing', total: 50_00 }),
        makeOrder({ id: '2', status: 'pending', total: 30_00 }),
        makeOrder({ id: '3', status: 'delivered', total: 100_00 }),
      ],
    };

    const kpis = buildDashboardKpis(data, 'es');
    const pendingKpi = kpis[3];

    // 50 + 30 = 80 dólares (8000 cents → "$80.00").
    expect(pendingKpi?.value).toContain('80');
    expect(pendingKpi?.tone).toBe('warning');
  });

  it('locale en → pluralización diferente en hint cuando aplica', () => {
    const data: AccountDashboardData = {
      ...emptyData(),
      orders: [
        makeOrder({ id: '1', status: 'processing', total: 50_00 }),
        makeOrder({ id: '2', status: 'pending', total: 30_00 }),
      ],
    };

    const esKpis = buildDashboardKpis(data, 'es');
    const enKpis = buildDashboardKpis(data, 'en');

    expect(esKpis[3]?.hint).toBe('2 por vencer');
    expect(enKpis[3]?.hint).toBe('2 pending');
  });
});
