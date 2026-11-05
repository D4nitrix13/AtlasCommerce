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

/**
 * Stat del rail de soporte del dashboard v4 (job-ticket redesign).
 *
 * A diferencia de `DashboardKpi` (grid de 4 tarjetas iguales), un stat
 * pertenece a un rail horizontal donde SOLO el primero lleva `emphasis:
 * 'hero'` (número dominante). Los stats sin dato significativo no se
 * construyen (empty-safe: la stat no existe, no se pinta "—").
 */
export interface DashboardStat {
  labelKey: TranslationKey;
  /** Ya formateado para mostrar (tabular-nums en el renderer). */
  value: string;
  /** Pista con significado real; los textos viven en i18n. */
  hintKey: TranslationKey | null;
  /** Parámetros para interpolar en la pista (`{press}`, `{count}`, ...). */
  hintParams: Record<string, string>;
  tone: 'positive' | 'neutral' | 'warning';
  emphasis: 'hero' | 'support';
}

/**
 * Etapas del rail de producción (tira de orden de trabajo).
 *
 * Mapea 1:1 sobre `OrderStatus` (menos `cancelled`, que vive fuera del
 * rail como estado terminal). El vocabulario es de taller de estampados:
 * recibido → en prensa → despacho → entrega.
 */
export type ProductionStage = 'received' | 'press' | 'transit' | 'done';

/**
 * Una orden activa (o histórica) ya normalizada para la tira de
 * producción. El renderer decide cuál es el hero; los datos no.
 */
export interface ProductionTicket {
  orderId: string;
  /** ISO 8601 — fecha del pedido. */
  placedAt: string;
  /** Estado crudo de la orden (incluye `cancelled`). */
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  /** Índice 0..3 dentro del rail; `-1` cuando está cancelada. */
  stageIndex: number;
  pieces: number;
  /** Total ya formateado (moneda). */
  totalLabel: string;
}
