/**
 * Module augmentation para Chart.js 4.
 *
 * Chart.js 4 typedefs no incluyen `cutout` en `ChartDataset<"doughnut", any>`
 * (la propiedad existe en runtime, type definitions quedaron cortas).
 * Esto extiende el type global para que TS acepte cutout en datasets
 * de tipo "doughnut" y "pie".
 */
import 'chart.js';

declare module 'chart.js' {
  interface DoughnutControllerChartOptions {}

  interface ChartDataset<TType extends ChartType, TData> {
    cutout?: string | number;
  }
}

export {};
