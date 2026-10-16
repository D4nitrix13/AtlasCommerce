/**
 * Server-side helper para el dashboard admin (`/admin/dashboard`).
 *
 * F3 — Reemplaza los 4 valores hardcoded del MetricCard y el dataset
 * del RevenueChart por fetch real al backend Laravel con la cookie
 * httpOnly de Sanctum stateful (CR2). Encapsula la lógica de fetch
 * + transform + error handling para que la página .astro se quede
 * con solo presentación.
 *
 * Endpoints consumidos (todos bajo `role:admin,staff` — F0.1):
 *   - GET /api/v1/admin/metrics/revenue/yearly   → 12 meses current vs prev
 *   - GET /api/v1/admin/metrics/sales/performance → 4 KPIs (Total vendido,
 *     Ticket promedio, Tasa de conversión, Devoluciones)
 *   - GET /api/v1/admin/metrics/customers/segments → 4 KPIs (Total
 *     clientes, Nuevos este mes, Tasa de retorno, Clientes activos)
 *   - GET /api/v1/admin/orders/recent?limit=10  → recent orders
 *   - GET /api/v1/admin/metrics/traffic/sources → traffic sources
 *   - GET /api/v1/admin/inventory/low-stock    → low stock count
 *
 * Multi-tenant: el middleware backend ya resolvió la tienda activa
 * vía `store.domain` antes de llegar a estas rutas. El locale del
 * storefront viene del middleware Astro (`Astro.locals.locale`).
 *
 * Failure modes (por endpoint, independientes — un 5xx en uno no
 * rompe los otros 5):
 *   - 4xx/5xx: el campo queda null, errors[endpoint]=true.
 *   - El caller renderiza el fallback visual del componente
 *     correspondiente (MetricCard acepta `value` como string).
 *
 * Spec: `odd/tasks/dashboards-redesign-and-auth.md` F3.1 + F3.2.
 */
export interface AdminDashboardData {
  metrics: AdminMetrics;
  recentOrders: RecentOrderAdminItem[];
  trafficSources: TrafficSourceItem[];
  errors: {
    metrics: boolean;
    recentOrders: boolean;
    trafficSources: boolean;
  };
}

export interface AdminMetrics {
  /** Suma del current year (12 meses). Null si /revenue/yearly falló. */
  totalRevenueCents: number | null;
  /** "Clientes activos" del endpoint customers/segments. */
  activeCustomers: number | null;
  /** Suma de visits de traffic sources. */
  totalVisits: number | null;
  /** Total vendido del mes actual (sales/performance[0] parsed). */
  totalSoldThisMonthCents: number | null;
  /** Devoluciones (sales/performance[3].value, ya formateado). */
  returnsRate: string | null;
  /** Yearly revenue chart dataset: 12 meses current vs previous. */
  revenueChart: RevenueChartData | null;
  lowStockCount: number | null;
}

export interface RevenueChartData {
  year: number;
  previousYear: number;
  labels: readonly string[];
  currentValues: readonly number[];
  previousValues: readonly number[];
}

export interface RecentOrderAdminItem {
  id: number;
  orderNumber: string;
  customerName: string;
  productSummary: string;
  statusLabel: string;
  statusColor: string;
  amountLabel: string;
}

export interface TrafficSourceItem {
  source: string;
  visits: number;
  percentage: number;
}

interface BackendRevenueYearlyResponse {
  data: {
    year: number;
    previous_year: number;
    labels: string[];
    current_year_values: number[];
    previous_year_values: number[];
  };
}

interface SalesKpi {
  label: string;
  value: string;
  change: string;
  trend: string;
}

interface BackendSalesPerformanceResponse {
  data: {
    metrics: SalesKpi[];
    days: string[];
    values: number[];
  };
}

interface BackendCustomerSegmentsResponse {
  data: {
    stats: SalesKpi[];
    segments: Array<{ label: string; value: number; color: string }>;
  };
}

interface BackendRecentOrdersResponse {
  data: Array<{
    id: number;
    order_number: string;
    customer_name: string;
    product_summary: string;
    status_label: string;
    status_color: string;
    amount_label: string;
  }>;
}

interface BackendTrafficSourcesResponse {
  data: {
    sources: Array<{ source: string; visits: number; percentage: number }>;
    top_pages: unknown[];
  };
}

interface BackendLowStockResponse {
  data: {
    count: number;
  };
}

/**
 * Parsea un value de SalesKpi a número (cents para $, raw para counts).
 * Helper para extraer campos del array `metrics` que devuelve
 * sales-performance y customers-segments.
 */
function parseKpiValue(raw: string, expectCents: boolean): number | null {
  if (raw === '—' || raw === '') {
    return null;
  }
  // "$1,234" → 1234 cents (si expectCents) o 12.34 dollars.
  // "1,234" → 1234 (raw count).
  const cleaned = raw.replace(/[$,\s%]/g, '');
  const n = Number(cleaned);
  if (Number.isNaN(n)) {
    return null;
  }
  return expectCents ? n * 100 : n;
}

/**
 * Helper principal. Llama al backend en paralelo y devuelve los
 * datos del dashboard admin con errors por endpoint.
 */
export async function getAdminDashboardData(
  origin: string,
  cookie: string
): Promise<AdminDashboardData> {
  // F0.x follow-up: `Origin` requerido por Sanctum stateful (CR2).
  // Ver getAccountDashboardData en lib/server/account.ts para el
  // rationale completo.
  const headers: HeadersInit = {
    Accept: 'application/json',
    Origin: origin,
  };
  if (cookie !== '') {
    headers.Cookie = cookie;
  }
  const fetchOptions: RequestInit = { headers };

  const [
    revenueYearlyResult,
    salesPerformanceResult,
    customerSegmentsResult,
    recentOrdersResult,
    trafficSourcesResult,
    lowStockResult,
  ] = await Promise.allSettled([
    fetch(new URL('/api/v1/admin/metrics/revenue/yearly', origin), fetchOptions),
    fetch(new URL('/api/v1/admin/metrics/sales/performance', origin), fetchOptions),
    fetch(new URL('/api/v1/admin/metrics/customers/segments', origin), fetchOptions),
    fetch(new URL('/api/v1/admin/orders/recent?limit=10', origin), fetchOptions),
    fetch(new URL('/api/v1/admin/metrics/traffic/sources', origin), fetchOptions),
    fetch(new URL('/api/v1/admin/inventory/low-stock', origin), fetchOptions),
  ]);

  // ─── Revenue chart + total del año ─────────────────────────
  let revenueChart: RevenueChartData | null = null;
  let totalRevenueCents: number | null = null;
  if (revenueYearlyResult.status === 'fulfilled' && revenueYearlyResult.value.ok) {
    const body = (await revenueYearlyResult.value.json()) as BackendRevenueYearlyResponse;
    revenueChart = {
      year: body.data.year,
      previousYear: body.data.previous_year,
      labels: body.data.labels,
      currentValues: body.data.current_year_values,
      previousValues: body.data.previous_year_values,
    };
    totalRevenueCents = body.data.current_year_values.reduce(
      (sum: number, v: number): number => sum + v,
      0
    );
  }

  // ─── Sales performance (Total vendido, Ticket promedio, etc.) ─
  let totalSoldThisMonthCents: number | null = null;
  let returnsRate: string | null = null;
  if (salesPerformanceResult.status === 'fulfilled' && salesPerformanceResult.value.ok) {
    const body = (await salesPerformanceResult.value.json()) as BackendSalesPerformanceResponse;
    const kpis = body.data.metrics ?? [];
    const totalSoldKpi = kpis.find((k) => k.label === 'Total vendido');
    const returnsKpi = kpis.find((k) => k.label === 'Devoluciones');
    if (totalSoldKpi !== undefined) {
      const parsed = parseKpiValue(totalSoldKpi.value, true);
      totalSoldThisMonthCents = parsed;
    }
    if (returnsKpi !== undefined) {
      returnsRate = returnsKpi.value;
    }
  }

  // ─── Customer segments (Clientes activos) ───────────────────
  let activeCustomers: number | null = null;
  if (customerSegmentsResult.status === 'fulfilled' && customerSegmentsResult.value.ok) {
    const body = (await customerSegmentsResult.value.json()) as BackendCustomerSegmentsResponse;
    const kpis = body.data.stats ?? [];
    const activeKpi = kpis.find((k) => k.label === 'Clientes activos');
    if (activeKpi !== undefined) {
      activeCustomers = parseKpiValue(activeKpi.value, false);
    }
  }

  // ─── Recent orders ──────────────────────────────────────────
  let recentOrders: RecentOrderAdminItem[] = [];
  let recentOrdersError = false;
  if (recentOrdersResult.status === 'fulfilled' && recentOrdersResult.value.ok) {
    const body = (await recentOrdersResult.value.json()) as BackendRecentOrdersResponse;
    recentOrders = (body.data ?? []).map((o) => ({
      id: o.id,
      orderNumber: o.order_number,
      customerName: o.customer_name,
      productSummary: o.product_summary,
      statusLabel: o.status_label,
      statusColor: o.status_color,
      amountLabel: o.amount_label,
    }));
  } else {
    recentOrdersError = true;
  }

  // ─── Traffic sources (sum of visits) ────────────────────────
  let totalVisits: number | null = null;
  let trafficSources: TrafficSourceItem[] = [];
  let trafficSourcesError = false;
  if (trafficSourcesResult.status === 'fulfilled' && trafficSourcesResult.value.ok) {
    const body = (await trafficSourcesResult.value.json()) as BackendTrafficSourcesResponse;
    trafficSources = (body.data.sources ?? []).map((t) => ({
      source: t.source,
      visits: t.visits,
      percentage: t.percentage,
    }));
    totalVisits = trafficSources.reduce((sum: number, t) => sum + t.visits, 0);
  } else {
    trafficSourcesError = true;
  }

  // ─── Low stock ──────────────────────────────────────────────
  let lowStockCount: number | null = null;
  if (lowStockResult.status === 'fulfilled' && lowStockResult.value.ok) {
    const body = (await lowStockResult.value.json()) as BackendLowStockResponse;
    lowStockCount = body.data.count;
  }

  // metricsError = true si revenue-yearly y sales-performance Y
  // customer-segments fallaron. El page usa 4 cards: si el dato de
  // una card no se pudo obtener, esa card muestra "—", el resto
  // sigue con datos reales.
  const metricsError =
    revenueYearlyResult.status === 'rejected' &&
    salesPerformanceResult.status === 'rejected' &&
    customerSegmentsResult.status === 'rejected';

  return {
    metrics: {
      totalRevenueCents,
      activeCustomers,
      totalVisits,
      totalSoldThisMonthCents,
      returnsRate,
      revenueChart,
      lowStockCount,
    },
    recentOrders,
    trafficSources,
    errors: {
      metrics: metricsError,
      recentOrders: recentOrdersError,
      trafficSources: trafficSourcesError,
    },
  };
}

/**
 * Formatea centavos a string monetario. Helper para el page (no para
 * el componente, que recibe `value` como string formateado).
 */
export function formatCentsAsCurrency(
  cents: number,
  currencyCode: string = 'USD',
  locale: string = 'en'
): string {
  const formatter = new Intl.NumberFormat(locale === 'es' ? 'es-NI' : 'en-US', {
    style: 'currency',
    currency: currencyCode,
    maximumFractionDigits: 0,
  });
  return formatter.format(cents / 100);
}
