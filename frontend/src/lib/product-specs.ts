/**
 * Formateo de la ficha técnica (tallas/capacidades + atributos EAV).
 *
 * Lógica pura, sin i18n: los labels de boolean los inyecta el caller
 * (el componente los resuelve con el diccionario del servidor).
 */
import type { AttributeValueMock } from '../data/catalog-mock';

/** Celda vacía compartida por las tablas de la ficha técnica. */
export const SPECS_EMPTY_CELL = '—';

export interface BooleanLabels {
  yes: string;
  no: string;
}

/**
 * Valor a mostrar para un atributo EAV según su tipo: `number` lee
 * `valueNumber`, `boolean` mapea a los labels provistos, y
 * `text`/`select` leen `value`. Valor ausente → celda vacía.
 */
export function formatAttributeValue(attr: AttributeValueMock, labels: BooleanLabels): string {
  switch (attr.type) {
    case 'boolean':
      return attr.valueBoolean === null
        ? SPECS_EMPTY_CELL
        : attr.valueBoolean
          ? labels.yes
          : labels.no;
    case 'number':
      return attr.valueNumber === null ? SPECS_EMPTY_CELL : String(attr.valueNumber);
    default:
      return attr.value === null || attr.value === '' ? SPECS_EMPTY_CELL : attr.value;
  }
}
