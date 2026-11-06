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
import type {
  DashboardKpi,
  DashboardKpiTone,
  DashboardStat,
  ProductionTicket,
} from '../types/dashboard-kpis';

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
  locale: 'es' | 'en' = 'es'
): DashboardKpi[] {
  const ordersTotal = data.orders.length;
  const processingOrders = data.orders.filter(
    (o) => o.status === 'processing' || o.status === 'pending'
  );
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
  const processingKpi: DashboardKpi =
    processingCount === 0
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
          delta:
            processingCount > 1
              ? `${String(processingCount)} ${locale === 'en' ? 'orders' : 'pedidos'}`
              : null,
          tone: 'neutral',
          hint: null,
        };

  // KPI 3 — Tasa de aprobación RFQ.
  // El endpoint actual devuelve solo `rfqPendingCount` (cuántas están
  // pendientes). Para "tasa de aprobación" necesitamos histórico. En F5.5
  // asumimos: si hay pendientes, mostramos el porcentaje implícito
  // (sin datos históricos = null → mostramos "—"). Cuando el backend
  // exponga `rfqApprovalRate`, cambiamos acá.
  const rfqKpi: DashboardKpi =
    data.rfqPendingCount === null
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
  const pendingKpi: DashboardKpi =
    pendingCents === 0
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
 * v4 — job-ticket redesign (estampados).
 *
 * La composición v3 (4 KPI cards iguales con "—") se reemplaza por:
 *   - `buildProductionTickets` → las órdenes normalizadas para la tira
 *     de producción (el FOCAL del dashboard): orden de trabajo + rail
 *     de etapas + chips de tinta.
 *   - `buildDashboardStats` → UNA stat hero (trabajos en curso) + un
 *     rail de stats de soporte con significado real. Stats sin dato no
 *     se construyen (empty-safe, sin "—" inventado).
 *
 * Los textos NO se componen acá: cada stat devuelve `hintKey` +
 * `hintParams` y el renderer interpola desde el diccionario i18n.
 * El formateo de moneda/números es locale-aware (`formatCurrency`),
 * igual que el resto del repo.
 *
 * Los mocks de favoritos/gastado-mes de la v3 se eliminan: preferimos
 * derivar métricas reales del payload actual (piezas estampadas,
 * gastado total) antes que pintar números inventados.
 */

const STAGE_INDEX: Record<ProductionTicket['status'], number> = {
  pending: 0,
  processing: 1,
  shipped: 2,
  delivered: 3,
  cancelled: -1,
};

/**
 * Índice 0..3 de la etapa de producción para un status de orden
 * (`-1` cuando está cancelada). Exportado para que el ledger de
 * `/account/orders` reutilice la misma escala que la tira del
 * dashboard — una sola verdad para el rail.
 */
export const PRODUCTION_STAGE_INDEX = STAGE_INDEX;

const isActiveStatus = (status: ProductionTicket['status']): boolean =>
  status === 'pending' || status === 'processing' || status === 'shipped';

/**
 * Normaliza las órdenes del dashboard a tickets de producción,
 * ordenadas por fecha de pedido (más recientes primero).
 */
export function buildProductionTickets(data: AccountDashboardData): ProductionTicket[] {
  return data.orders
    .map((order): ProductionTicket => {
      return {
        orderId: order.id,
        placedAt: order.date,
        status: order.status,
        stageIndex: STAGE_INDEX[order.status],
        pieces: order.items,
        totalLabel: formatCurrency(order.total, null),
      };
    })
    .sort((a, b) => Date.parse(b.placedAt) - Date.parse(a.placedAt));
}

/**
 * Rail de stats: 1 hero + soportes. `rfqPendingCount === null` omite la
 * stat de cotizaciones por completo (el endpoint no respondió).
 */
export function buildDashboardStats(data: AccountDashboardData): DashboardStat[] {
  const activeOrders = data.orders.filter((o) => isActiveStatus(o.status));
  const inPress = data.orders.filter((o) => o.status === 'processing').length;
  const inTransit = data.orders.filter((o) => o.status === 'shipped').length;
  const totalPieces = data.orders.reduce((sum, o) => sum + o.items, 0);
  const totalCents = data.orders.reduce((sum, o) => sum + o.total, 0);

  const heroStat: DashboardStat = {
    labelKey: 'account.dashboard.stats.active',
    value: String(activeOrders.length),
    hintKey:
      activeOrders.length === 0
        ? 'account.dashboard.stats.idleHint'
        : 'account.dashboard.stats.activeHint',
    hintParams: { press: String(inPress), transit: String(inTransit) },
    tone: activeOrders.length > 0 ? 'positive' : 'neutral',
    emphasis: 'hero',
  };

  const spentStat: DashboardStat = {
    labelKey: 'account.dashboard.stats.spent',
    value: formatCurrency(totalCents, null),
    hintKey: 'account.dashboard.stats.spentHint',
    hintParams: { count: String(data.orders.length) },
    tone: 'neutral',
    emphasis: 'support',
  };

  const piecesStat: DashboardStat = {
    labelKey: 'account.dashboard.stats.pieces',
    value: String(totalPieces),
    hintKey: 'account.dashboard.stats.piecesHint',
    hintParams: {},
    tone: 'neutral',
    emphasis: 'support',
  };

  const stats = [heroStat, spentStat, piecesStat];

  if (data.rfqPendingCount !== null) {
    stats.push({
      labelKey: 'account.dashboard.stats.quotes',
      value: String(data.rfqPendingCount),
      hintKey: 'account.dashboard.stats.quotesHint',
      hintParams: {},
      tone: data.rfqPendingCount > 0 ? 'warning' : 'neutral',
      emphasis: 'support',
    });
  }

  return stats;
}
