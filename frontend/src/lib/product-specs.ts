/**
 * Formateo de la ficha técnica (tallas/capacidades + atributos EAV).
 *
 * Lógica pura, sin i18n: los labels de boolean los inyecta el caller
 * (el componente los resuelve con el diccionario del servidor).
 */
import type { AttributeValueMock } from '../data/catalog-mock';
import type { ProductAttribute, ProductSize } from '../types/catalog';

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

/** Fila label/value para `ProductSpecsTable`. */
export interface ProductSpecRow {
  label: string;
  value: string;
}

/**
 * Valor a mostrar para un atributo EAV del storefront (shape del
 * `StorefrontProductResource`): texto/número/booleano según lo que
 * venga poblado, con la unidad apilada ("11 oz").
 */
export function formatProductAttributeValue(attr: ProductAttribute, labels: BooleanLabels): string {
  let raw = SPECS_EMPTY_CELL;
  if (typeof attr.value === 'string' && attr.value !== '') raw = attr.value;
  else if (attr.valueNumber !== null && attr.valueNumber !== undefined) raw = String(attr.valueNumber);
  else if (attr.valueBoolean !== null && attr.valueBoolean !== undefined) {
    raw = attr.valueBoolean ? labels.yes : labels.no;
  }
  const hasUnit = typeof attr.unit === 'string' && attr.unit !== '';
  return hasUnit && raw !== SPECS_EMPTY_CELL ? `${raw} ${attr.unit}` : raw;
}

/**
 * Filas de la ficha técnica real: tallas con medida (value + unit) y
 * atributos EAV. Las tallas sin medida no aportan fila (ya son el
 * selector de tallas del aside).
 */
export function buildProductSpecRows(
  attributes: readonly ProductAttribute[] | undefined,
  sizes: readonly ProductSize[] | undefined,
  labels: BooleanLabels,
): ProductSpecRow[] {
  const sizeRows = (sizes ?? [])
    .filter((size) => size.value !== null && size.value !== undefined)
    .map((size) => ({
      label: size.label,
      value: `${size.value}${size.unit != null && size.unit !== '' ? ` ${size.unit}` : ''}`,
    }));
  const attributeRows = (attributes ?? []).map((attr) => ({
    label: attr.label,
    value: formatProductAttributeValue(attr, labels),
  }));
  return [...sizeRows, ...attributeRows];
}
