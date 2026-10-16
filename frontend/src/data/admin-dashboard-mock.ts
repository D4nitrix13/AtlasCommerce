/**
 * Mock data para el dashboard v3 (Bonfire mockup 11).
 *
 * Datos estaticos que alimentan el `<DashboardV3Section>` hasta que el
 * backend exponga endpoints v3-friendly. Cuando eso pase, el caller
 * sustituye los arrays de este archivo por fetch real y mantiene la
 * forma (mismas keys, mismos tipos) — sin tocar el componente.
 *
 * i18n: las labels que se traducen vienen via `getServerDictionary`
 * del caller. Este archivo NO importa el diccionario para evitar
 * dependencia circular y mantenerlo puro-datos (separable).
 */

export interface RevenueChartPoint {
  x: number;
  y: number;
}

export interface TopProductRow {
  name: string;
  unitsLabel: string;
  revenueLabel: string;
}

export type OrderStatusKey = 'production' | 'paid' | 'shipped' | 'delivered';

export interface RecentOrderRow {
  orderNumber: string;
  customer: string;
  total: string;
  status: OrderStatusKey;
  /** Texto "ago" ya formateado por el caller (e.g. "8 min"). */
  ago: string;
}

export interface CategorySegment {
  value: number;
  color: string;
  label: string;
}

export type AlertSeverity = 'warning' | 'info';

export interface AlertItem {
  severity: AlertSeverity;
  title: string;
  body: string;
}

/**
 * Genera 30 puntos (x, y) para el RevenueChartV3 con viewBox 0 0 680 210.
 *
 * Patron: tendencia levemente creciente + ruido deterministico. Los
 * valores entre 25 y 130 se mapean al rango Y visible del chart.
 *
 * Por que `Math.random()`-free: la salida debe ser identica entre
 * SSR runs para evitar hydration mismatch. Los datos son los mismos
 * siempre que el archivo no se edite.
 */
export function buildRevenuePoints(): readonly RevenueChartPoint[] {
  const WIDTH = 680;
  const COUNT = 30;
  const values = [
    32, 38, 28, 45, 55, 48, 62, 70, 58, 65, 75, 82, 70, 78, 88, 80, 92, 95, 88, 95, 100, 92, 98,
    105, 110, 102, 108, 115, 120, 128,
  ];
  const minValue = 25;
  const maxValue = 130;
  const yMin = 30;
  const yMax = 180;

  return values.map((value, i) => {
    const x = (WIDTH / (COUNT - 1)) * i;
    const ratio = (value - minValue) / (maxValue - minValue);
    const y = yMax - ratio * (yMax - yMin);
    return { x, y };
  });
}

export const TOP_PRODUCTS_MOCK: readonly TopProductRow[] = [
  { name: 'Playera Premium', unitsLabel: '342', revenueLabel: '$6.148' },
  { name: 'Hoodie French Terry', unitsLabel: '198', revenueLabel: '$4.752' },
  { name: 'Gorra Bordada', unitsLabel: '142', revenueLabel: '$2.260' },
  { name: 'Taza Personalizada', unitsLabel: '128', revenueLabel: '$1.408' },
  { name: 'Sudadera Kitsune', unitsLabel: '89', revenueLabel: '$4.272' },
];

/**
 * Devuelve las ordenes recientes con el `ago` ya formateado segun idioma.
 * El caller pasa las 3 keys i18n para "min"/"hour"/"hours" y arma el
 * sufijo (e.g. "8 min", "1 h", "3 h").
 */
export function buildRecentOrdersMock(
  minSuffix: string,
  hourSingularSuffix: string,
  hourPluralSuffix: string
): readonly RecentOrderRow[] {
  return [
    {
      orderNumber: '#AT-2481',
      customer: 'Colegio San Martín',
      total: '$1.240',
      status: 'production',
      ago: `8 ${minSuffix}`,
    },
    {
      orderNumber: '#AT-2480',
      customer: 'Estudio Norte',
      total: '$680',
      status: 'paid',
      ago: `24 ${minSuffix}`,
    },
    {
      orderNumber: '#AT-2479',
      customer: 'Fundación Arco',
      total: '$3.920',
      status: 'shipped',
      ago: `1 ${hourSingularSuffix}`,
    },
    {
      orderNumber: '#AT-2478',
      customer: 'Marta Quiroga',
      total: '$342',
      status: 'paid',
      ago: `3 ${hourPluralSuffix}`,
    },
    {
      orderNumber: '#AT-2477',
      customer: 'Hotel Vista Hermosa',
      total: '$2.180',
      status: 'delivered',
      ago: `5 ${hourPluralSuffix}`,
    },
  ];
}

/** Categorias del donut con sus colores canonicos (replica mockup 11). */
export const CATEGORY_SEGMENTS_MOCK: readonly CategorySegment[] = [
  { value: 42, color: 'var(--primary, #2d9d8e)', label: '' },
  { value: 24, color: '#EAB24B', label: '' },
  { value: 16, color: '#951318', label: '' },
  { value: 18, color: '#9FB0BC', label: '' },
];

/** Helpers que inyectan `label` en cada categoria via caller. */
export function buildCategorySegments(labels: {
  tshirts: string;
  hoodies: string;
  caps: string;
  accessories: string;
}): readonly CategorySegment[] {
  return [
    { value: 42, color: 'var(--primary, #2d9d8e)', label: labels.tshirts },
    { value: 24, color: '#EAB24B', label: labels.hoodies },
    { value: 16, color: '#951318', label: labels.caps },
    { value: 18, color: '#9FB0BC', label: labels.accessories },
  ];
}

export function buildActivityAlerts(labels: {
  rfqTitle: string;
  rfqBody: string;
  lowStockTitle: string;
  lowStockBody: string;
  shippingTitle: string;
  shippingBody: string;
}): readonly AlertItem[] {
  return [
    { severity: 'warning', title: labels.rfqTitle, body: labels.rfqBody },
    { severity: 'warning', title: labels.lowStockTitle, body: labels.lowStockBody },
    { severity: 'info', title: labels.shippingTitle, body: labels.shippingBody },
  ];
}
