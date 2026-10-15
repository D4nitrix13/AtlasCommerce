/**
 * Tipos compartidos para el dashboard de cuenta (F5.5).
 *
 * Las KPIs del top row derivan de `AccountDashboardData` (lib/server/account)
 * pero se exponen ya "para mostrar": cada `DashboardKpi` lleva strings
 * localized-friendly (`label`, `value`, `delta`, `hint`) que el
 * componente `KpiCard.astro` renderiza directamente.
 *
 * Por qué un `Kpi | null`: cuando el endpoint backend devuelve null
 * (ej: rfqPendingCount null porque /rfq no existe), la KPI no tiene
 * dato significativo que mostrar — el renderer pinta "—".
 */

import type { TranslationKey } from '../i18n/types';

/**
 * Tono del delta/hint del KPI.
 *
 * - `positive`  → verde (métricas mejorando).
 * - `neutral`   → gris (informativo, sin juicio).
 * - `warning`   → ámbar (atención).
 */
export type DashboardKpiTone = 'positive' | 'neutral' | 'warning';

/**
 * Una sola KPI ya calculada y lista para mostrar.
 *
 * `value` siempre es string (ej: `"24"`, `"$1,240"`, `"—"`) — el helper
 * `buildDashboardKpis` se encarga del formateo según locale/moneda.
 *
 * `delta` se usa para mostrar "+6 este mes" o "+4% vs Q3" debajo del
 * valor. `tone` define el color del delta (positive → verde, neutral →
 * gris, etc). `hint` es el fallback cuando no hay delta significativo
 * (ej: "Llega el 24 oct").
 */
export interface DashboardKpi {
  labelKey: TranslationKey;
  value: string;
  delta: string | null;
  tone: DashboardKpiTone;
  hint: string | null;
}
