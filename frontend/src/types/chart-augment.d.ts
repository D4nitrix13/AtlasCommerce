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
  // Interface declarada vacía intencionalmente: Chart.js espera una
  // interface con ese nombre para poder mergear extensions futuras sin
  // tocar este archivo. No es laxo rules, es el contrato de augmentation.
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface DoughnutControllerChartOptions {}

  // `TType` y `TData` son los generics del ChartDataset original de
  // Chart.js. Como esta es una module augmentation, tenemos que mantener
  // la firma exacta para que TS haga el merge correctamente. No se usan
  // en el body pero son parte del contrato.
  interface ChartDataset<_TType extends ChartType, _TData> {
    cutout?: string | number;
  }
}

export {};
