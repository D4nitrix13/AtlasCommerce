// @vitest-environment node
import { describe, it, expect } from 'vitest';
import { buildProductionTickets, buildDashboardStats } from '../../../src/data/dashboard-kpis';
import type { AccountDashboardData } from '../../../src/lib/server/account';
import type { RecentOrderMock } from '../../../src/data/account-mock';
import type { ProductionTicket } from '../../../src/types/dashboard-kpis';

/**
 * Job-ticket dashboard data layer (production strip + stat rail).
 *
 * Cubre:
 *   - `buildProductionTickets`: mapeo status → stageIndex, orden por
 *     fecha desc, piezas y total formateado.
 *   - `buildDashboardStats`: hero = órdenes activas (pending/processing/
 *     shipped), soportes = gastado y piezas; empty-safe en cotizaciones
 *     (rfqPendingCount null → la stat no existe, no se pinta "—").
 */
describe('data/dashboard-kpis (job-ticket)', () => {
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

  function makeOrder(overrides: Partial<RecentOrderMock> = {}): RecentOrderMock {
    return {
      id: '1',
      date: '2026-10-12T00:00:00Z',
      status: 'delivered',
      total: 0,
      items: 1,
      ...overrides,
    };
  }

  describe('buildProductionTickets', () => {
    it('sin órdenes → array vacío', () => {
      expect(buildProductionTickets(emptyData())).toEqual([]);
    });

    it('mapea cada status a su stageIndex del rail', () => {
      const cases: ProductionTicket['status'][] = [
        'pending',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
      ];
      const expected = [0, 1, 2, 3, -1];

      cases.forEach((status, i) => {
        const tickets = buildProductionTickets({
          ...emptyData(),
          orders: [makeOrder({ id: String(i), status })],
        });

        expect(tickets[0]?.stageIndex).toBe(expected[i]);
        expect(tickets[0]?.status).toBe(status);
      });
    });

    it('ordena por fecha de pedido descendente (más recientes primero)', () => {
      const tickets = buildProductionTickets({
        ...emptyData(),
        orders: [
          makeOrder({ id: 'old', date: '2026-09-01T00:00:00Z' }),
          makeOrder({ id: 'new', date: '2026-11-01T00:00:00Z' }),
          makeOrder({ id: 'mid', date: '2026-10-01T00:00:00Z' }),
        ],
      });

      expect(tickets.map((ticket) => ticket.orderId)).toEqual(['new', 'mid', 'old']);
    });

    it('arrastra piezas y total ya formateado', () => {
      const tickets = buildProductionTickets({
        ...emptyData(),
        orders: [makeOrder({ items: 7, total: 125_000 })],
      });

      expect(tickets[0]?.pieces).toBe(7);
      expect(tickets[0]?.totalLabel).toContain('1,250');
    });
  });

  describe('buildDashboardStats', () => {
    it('sin datos → hero en cero con pista idle, más los soportes', () => {
      const stats = buildDashboardStats(emptyData());

      expect(stats[0]).toMatchObject({
        emphasis: 'hero',
        value: '0',
        tone: 'neutral',
        hintKey: 'account.dashboard.stats.idleHint',
      });
      expect(stats.filter((stat) => stat.emphasis === 'hero')).toHaveLength(1);
      expect(stats.map((stat) => stat.labelKey)).toEqual([
        'account.dashboard.stats.active',
        'account.dashboard.stats.spent',
        'account.dashboard.stats.pieces',
      ]);
    });

    it('el hero cuenta solo órdenes activas (pending/processing/shipped)', () => {
      const stats = buildDashboardStats({
        ...emptyData(),
        orders: [
          makeOrder({ id: '1', status: 'pending' }),
          makeOrder({ id: '2', status: 'processing' }),
          makeOrder({ id: '3', status: 'shipped' }),
          makeOrder({ id: '4', status: 'delivered' }),
          makeOrder({ id: '5', status: 'cancelled' }),
        ],
      });

      expect(stats[0]?.value).toBe('3');
      expect(stats[0]?.tone).toBe('positive');
      expect(stats[0]?.hintParams?.press).toBe('1');
      expect(stats[0]?.hintParams?.transit).toBe('1');
    });

    it('acumula piezas y gastado total en los soportes', () => {
      const stats = buildDashboardStats({
        ...emptyData(),
        orders: [makeOrder({ items: 2, total: 10_000 }), makeOrder({ items: 3, total: 20_000 })],
      });

      const spent = stats.find((stat) => stat.labelKey === 'account.dashboard.stats.spent');
      const pieces = stats.find((stat) => stat.labelKey === 'account.dashboard.stats.pieces');

      expect(pieces?.value).toBe('5');
      expect(spent?.value).toContain('300');
    });

    it('rfqPendingCount null → la stat de cotizaciones no existe (empty-safe)', () => {
      const stats = buildDashboardStats(emptyData());

      expect(stats.some((stat) => stat.labelKey === 'account.dashboard.stats.quotes')).toBe(false);
    });

    it('rfqPendingCount numérico → agrega la stat con tono warning si hay pendientes', () => {
      const stats = buildDashboardStats({ ...emptyData(), rfqPendingCount: 4 });

      const quotes = stats.find((stat) => stat.labelKey === 'account.dashboard.stats.quotes');

      expect(quotes?.value).toBe('4');
      expect(quotes?.tone).toBe('warning');
      expect(quotes?.emphasis).toBe('support');
    });
  });
});
