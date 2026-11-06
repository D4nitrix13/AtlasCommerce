// @vitest-environment node
import { describe, it, expect } from 'vitest';
import {
  DEFAULT_ORDERS_SHOWN,
  applyOrdersQuery,
  buildOrdersHref,
  countOrdersByStatus,
  parseOrdersQuery,
  type OrdersQuery,
} from '../../../src/lib/orders-filters';

/**
 * Job-ticket ledger query model (status filter + sort + shown).
 *
 * Cubre:
 *   - `parseOrdersQuery`: defaults tolerantes a params inválidos.
 *   - `applyOrdersQuery`: filtro real por status, sort por fecha y
 *     corte por `shown`.
 *   - `buildOrdersHref`: hrefs SSR que preservan el estado actual,
 *     omiten defaults (URL limpia) y resetean `shown` al cambiar
 *     filtro u orden.
 *   - `countOrdersByStatus`: conteos para los pills de filtro.
 */

interface Row {
  id: string;
  status: string;
  date: string;
}

function row(id: string, status: string, date: string): Row {
  return { id, status, date };
}

const ROWS: Row[] = [
  row('1', 'delivered', '2026-10-01T12:00:00Z'),
  row('2', 'processing', '2026-10-03T12:00:00Z'),
  row('3', 'pending', '2026-10-02T12:00:00Z'),
  row('4', 'cancelled', '2026-10-05T12:00:00Z'),
  row('5', 'shipped', '2026-10-04T12:00:00Z'),
  row('6', 'delivered', '2026-09-30T12:00:00Z'),
  row('7', 'delivered', '2026-10-06T12:00:00Z'),
];

const BASE: OrdersQuery = { status: 'all', sort: 'recent', shown: DEFAULT_ORDERS_SHOWN };

describe('lib/orders-filters', () => {
  describe('parseOrdersQuery', () => {
    it('sin params → defaults', () => {
      expect(parseOrdersQuery(new URLSearchParams())).toEqual(BASE);
    });

    it('parsea status, sort y shown válidos', () => {
      const query = parseOrdersQuery(new URLSearchParams('status=shipped&sort=oldest&shown=12'));
      expect(query).toEqual({ status: 'shipped', sort: 'oldest', shown: 12 });
    });

    it('ignora status y sort inválidos', () => {
      const query = parseOrdersQuery(new URLSearchParams('status=zzz&sort=nope'));
      expect(query).toEqual(BASE);
    });

    it('ignora shown no numérico o no positivo', () => {
      expect(parseOrdersQuery(new URLSearchParams('shown=abc')).shown).toBe(DEFAULT_ORDERS_SHOWN);
      expect(parseOrdersQuery(new URLSearchParams('shown=0')).shown).toBe(DEFAULT_ORDERS_SHOWN);
      expect(parseOrdersQuery(new URLSearchParams('shown=-4')).shown).toBe(DEFAULT_ORDERS_SHOWN);
    });
  });

  describe('applyOrdersQuery', () => {
    const ALL: OrdersQuery = { ...BASE, shown: ROWS.length };

    it('status=all no filtra', () => {
      expect(applyOrdersQuery(ROWS, ALL)).toHaveLength(ROWS.length);
    });

    it('filtra por status', () => {
      const result = applyOrdersQuery(ROWS, { ...ALL, status: 'delivered' });
      expect(result.map((r) => r.id)).toEqual(['7', '1', '6']);
    });

    it('sort=recent ordena por fecha descendente', () => {
      const result = applyOrdersQuery(ROWS, ALL);
      expect(result.map((r) => r.id)).toEqual(['7', '4', '5', '2', '3', '1', '6']);
    });

    it('sort=oldest ordena por fecha ascendente', () => {
      const result = applyOrdersQuery(ROWS, { ...ALL, sort: 'oldest' });
      expect(result.map((r) => r.id)).toEqual(['6', '1', '3', '2', '5', '4', '7']);
    });

    it('recorta a `shown` después de filtrar y ordenar', () => {
      const result = applyOrdersQuery(ROWS, { ...BASE, shown: 3 });
      expect(result.map((r) => r.id)).toEqual(['7', '4', '5']);
    });

    it('combinación filtro + sort + shown', () => {
      const result = applyOrdersQuery(ROWS, {
        status: 'delivered',
        sort: 'oldest',
        shown: 2,
      });
      expect(result.map((r) => r.id)).toEqual(['6', '1']);
    });
  });

  describe('buildOrdersHref', () => {
    it('todo default → query string vacía', () => {
      expect(buildOrdersHref(BASE, {})).toBe('');
    });

    it('serializa solo el status cambiado', () => {
      expect(buildOrdersHref(BASE, { status: 'shipped' })).toBe('?status=shipped');
    });

    it('serializa solo el sort cambiado', () => {
      expect(buildOrdersHref(BASE, { sort: 'oldest' })).toBe('?sort=oldest');
    });

    it('serializa shown cuando supera el default', () => {
      expect(buildOrdersHref(BASE, { shown: 12 })).toBe('?shown=12');
    });

    it('acumula params activos y omite defaults', () => {
      const current: OrdersQuery = { status: 'shipped', sort: 'oldest', shown: 12 };
      expect(buildOrdersHref(current, { shown: 18 })).toBe('?status=shipped&sort=oldest&shown=18');
    });

    it('cambiar status u orden resetea shown (salvo patch explícito)', () => {
      const current: OrdersQuery = { status: 'all', sort: 'recent', shown: 12 };
      expect(buildOrdersHref(current, { status: 'delivered' })).toBe('?status=delivered');
      expect(buildOrdersHref(current, { sort: 'oldest' })).toBe('?sort=oldest');
      expect(buildOrdersHref(current, { status: 'delivered', shown: 12 })).toBe(
        '?status=delivered&shown=12'
      );
    });

    it('volver a status=all limpia el param', () => {
      const current: OrdersQuery = { status: 'shipped', sort: 'recent', shown: 6 };
      expect(buildOrdersHref(current, { status: 'all' })).toBe('');
    });
  });

  describe('countOrdersByStatus', () => {
    it('cuenta por status y el total en all', () => {
      expect(countOrdersByStatus(ROWS)).toEqual({
        all: 7,
        pending: 1,
        processing: 1,
        shipped: 1,
        delivered: 3,
        cancelled: 1,
      });
    });
  });
});
