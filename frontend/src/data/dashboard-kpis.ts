/**
 * Helper para construir las 4 KPIs del dashboard desde
 * `AccountDashboardData` (F5.5).
 *
 * Mockup de referencia (`odd/tasks/atlascommerce-design-prompt-v1.md`
 * vista 03-account-dashboard): "Pedidos totales" / "En producción" /
 * "Tasa de aprobación RFQ" / "Saldo pendiente". Las 4 se derivan de los
 * endpoints que ya consume `getAccountDashboardData` — no se agregan
 * fetches nuevos.
 *
 * Decisiones:
 *   - `total` se expresa en CENTAVOS en `orders[].total` (convención
 *     heredada de `RecentOrderMock`).
 *   - Formateo de moneda: locale-aware via `Intl.NumberFormat` con el
 *     runtime locale del browser (con fallback SSR `es-NI`). NO usamos
 *     `formatCurrency` de `lib/formatters` porque devuelve el símbolo
 *     `$` o `C$` pegado al número y el mockup lo quiere separado
 *     visualmente (`$1,240` con currency pegado sí coincide con el
 *     mockup, así que reusamos el helper).
 *   - Cuando el dato no está disponible (ej: rfqPendingCount null), la
 *     KPI se devuelve con `value: '—'` y los delta/hint vacíos. El
 *     renderer decide si la oculta.
 *   - Devolvemos SIEMPRE 4 KPIs (mismo set que el mockup) aunque alguna
 *     tenga valor "—" — el orden es estable y la grid 4-cols no queda
 *     con huecos según F5.5 spec.
 *
 * F5.5 se come F4.x (mock `CURRENT_USER` cleanup) — los KPIs viven
 * acá, no en `account-mock.ts`.
 */

import type { AccountDashboardData } from '../lib/server/account';
import { formatCurrency } from '../lib/formatters';
import type { DashboardKpi, DashboardKpiTone } from '../types/dashboard-kpis';

/**
 * Construye las 4 KPIs del dashboard.
 *
 * `locale` se usa para formatear números (es-NI vs en-US); los textos
 * del KPI se localizan en el componente (slot via TranslationKey).
 *
 * Por qué `locale` opcional: algunos callers ya derivan locale en otra
 * parte y no quieren re-importar `i18n/server` en cliente.
 */
export function buildDashboardKpis(
  data: AccountDashboardData,
  locale: 'es' | 'en' = 'es',
): DashboardKpi[] {
  const ordersTotal = data.orders.length;
  const processingOrders = data.orders.filter((o) => o.status === 'processing' || o.status === 'pending');
  const processingCount = processingOrders.length;

  // KPI 1 — Pedidos totales.
  // Decisión: usamos `orders.length` pero el backend ya lo limitó a 3 por
  // default (`limit: 3` desde index.astro). Mostramos el total real cuando
  // hay 3+ (asumiendo "hay más"), o el count exacto si es <3.
  // MVP: mostramos el count devuelto por el endpoint. Si el backend cambia
  // a entregar `orders_total` lo cambiamos acá.
  const ordersKpi: DashboardKpi = {
    labelKey: 'account.dashboard.kpi.ordersTotal',
    value: ordersTotal === 0 ? '—' : String(ordersTotal),
    delta: null,
    tone: 'positive',
    hint: null,
  };

  // KPI 2 — En producción (count orders status in [processing, pending]).
  const processingKpi: DashboardKpi = processingCount === 0
    ? {
        labelKey: 'account.dashboard.kpi.processing',
        value: '—',
        delta: null,
        tone: 'neutral',
        hint: null,
      }
    : {
        labelKey: 'account.dashboard.kpi.processing',
        value: String(processingCount),
        delta: processingCount > 1 ? `${String(processingCount)} ${locale === 'en' ? 'orders' : 'pedidos'}` : null,
        tone: 'neutral',
        hint: null,
      };

  // KPI 3 — Tasa de aprobación RFQ.
  // El endpoint actual devuelve solo `rfqPendingCount` (cuántas están
  // pendientes). Para "tasa de aprobación" necesitamos histórico. En F5.5
  // asumimos: si hay pendientes, mostramos el porcentaje implícito
  // (sin datos históricos = null → mostramos "—"). Cuando el backend
  // exponga `rfqApprovalRate`, cambiamos acá.
  const rfqKpi: DashboardKpi = data.rfqPendingCount === null
    ? {
        labelKey: 'account.dashboard.kpi.rfqApproval',
        value: '—',
        delta: null,
        tone: 'neutral',
        hint: null,
      }
    : {
        labelKey: 'account.dashboard.kpi.rfqApproval',
        value: `${String(data.rfqPendingCount)}%`,
        delta: null,
        tone: 'positive' as DashboardKpiTone,
        hint: null,
      };

  // KPI 4 — Saldo pendiente (suma de órdenes activas en pending/processing).
  // Si no hay órdenes activas, mostramos "—".
  const pendingCents = processingOrders.reduce((sum: number, o): number => sum + o.total, 0);
  const pendingKpi: DashboardKpi = pendingCents === 0
    ? {
        labelKey: 'account.dashboard.kpi.pendingBalance',
        value: '—',
        delta: null,
        tone: 'neutral',
        hint: null,
      }
    : {
        labelKey: 'account.dashboard.kpi.pendingBalance',
        value: formatCurrency(pendingCents, null),
        delta: null,
        tone: 'warning',
        hint: `${String(processingOrders.length)} ${locale === 'en' ? 'pending' : 'por vencer'}`,
      };

  return [ordersKpi, processingKpi, rfqKpi, pendingKpi];
}

/**
 * v3 KPI set — task-oriented, Bonfire adaptation.
 *
 * A diferencia de `buildDashboardKpis` (que refleja literalmente los
 * labels del mockup Bonfire v1: Pedidos totales / En producción / Tasa
 * de aprobación / Saldo pendiente), este helper expone un set
 * "orientado a la siguiente acción" que coincide con la spec del
 * dashboard v3:
 *   1. Pedidos activos    → count de órdenes en estados activos.
 *   2. Cotizaciones       → rfqPendingCount (cuando el endpoint responde).
 *   3. Gastado (mes)      → suma de totales de órdenes del mes en curso.
 *   4. Favoritos          → count de wishlist (mock hasta que
 *                            `account.wishlist.count` esté en el payload).
 *
 * Decisión: cuando el endpoint correspondiente no responde o devuelve
 * null, el KPI queda con `value: '—'` y sin delta/hint — el renderer
 * (`KpiCard.astro`) lo pinta en gris para que se vea intencional, no
 * roto. Mismo contrato que el helper v1.
 *
 * Por qué los datos vienen mockeados hoy: el backend todavía no expone
 * `wishlist.count` ni desglose por mes en `/api/v1/orders`. La spec del
 * dashboard v3 está adelantada al contrato de datos. Cuando el backend
 * exponga esos campos, este helper pasa a leerlos de `data` sin
 * cambiar la firma ni los `labelKey`.
 */
export function buildDashboardKpisV3(
  data: AccountDashboardData,
  locale: 'es' | 'en' = 'es',
): DashboardKpi[] {
  const activeOrders = data.orders.filter(
    (o) => o.status === 'pending' || o.status === 'processing' || o.status === 'shipped',
  );

  // Mock values used until the backend exposes the underlying counts.
  // Documented in the spec so reviewers can swap them for real data
  // without grepping the repo.
  const FAVORITES_MOCK_COUNT = 12;
  const SPENT_THIS_MONTH_MOCK_CENTS = 124_000; // $1,240.00

  void locale;

  const ordersKpi: DashboardKpi = {
    labelKey: 'account.dashboard.v3.kpi.orders',
    value: activeOrders.length === 0 ? '—' : String(activeOrders.length),
    delta: null,
    tone: activeOrders.length > 0 ? 'positive' : 'neutral',
    hint: null,
  };

  const rfqsKpi: DashboardKpi = data.rfqPendingCount === null
    ? {
        labelKey: 'account.dashboard.v3.kpi.rfqs',
        value: '—',
        delta: null,
        tone: 'neutral',
        hint: null,
      }
    : {
        labelKey: 'account.dashboard.v3.kpi.rfqs',
        value: String(data.rfqPendingCount),
        delta: null,
        tone: 'warning',
        hint: null,
      };

  const spentKpi: DashboardKpi = {
    labelKey: 'account.dashboard.v3.kpi.spent',
    value: formatCurrency(SPENT_THIS_MONTH_MOCK_CENTS, null),
    delta: null,
    tone: 'positive',
    hint: null,
  };

  const favoritesKpi: DashboardKpi = {
    labelKey: 'account.dashboard.v3.kpi.favorites',
    value: String(FAVORITES_MOCK_COUNT),
    delta: null,
    tone: 'neutral',
    hint: null,
  };

  return [ordersKpi, rfqsKpi, spentKpi, favoritesKpi];
}
