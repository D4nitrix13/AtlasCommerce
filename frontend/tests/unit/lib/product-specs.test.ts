/**
 * Tests del formateo de valores de la ficha técnica.
 *
 * `formatAttributeValue` despacha según el tipo EAV del atributo:
 * `boolean` usa los labels provistos (i18n), `number` lee
 * `valueNumber`, `text`/`select` leen `value`. Cualquier valor
 * ausente colapsa a la celda vacía.
 */
import { describe, expect, it } from 'vitest';
import {
  SPECS_EMPTY_CELL,
  buildProductSpecRows,
  formatAttributeValue,
  formatProductAttributeValue,
  type BooleanLabels,
} from '../../../src/lib/product-specs';
import type { AttributeValueMock } from '../../../src/data/catalog-mock';

const LABELS: BooleanLabels = { yes: 'Sí', no: 'No' };

function attr(overrides: Partial<AttributeValueMock>): AttributeValueMock {
  return {
    id: 1,
    productId: 1,
    slug: 'attr',
    label: 'Attr',
    type: 'text',
    unit: null,
    value: null,
    valueNumber: null,
    valueBoolean: null,
    ...overrides,
  };
}

describe('formatAttributeValue', () => {
  it('text: devuelve el valor de texto', () => {
    expect(formatAttributeValue(attr({ type: 'text', value: 'Cerámica' }), LABELS)).toBe('Cerámica');
  });

  it('select: devuelve el valor de texto', () => {
    expect(formatAttributeValue(attr({ type: 'select', value: 'Mate' }), LABELS)).toBe('Mate');
  });

  it('text: value null colapsa a la celda vacía', () => {
    expect(formatAttributeValue(attr({ type: 'text', value: null }), LABELS)).toBe(SPECS_EMPTY_CELL);
  });

  it('text: value vacío colapsa a la celda vacía', () => {
    expect(formatAttributeValue(attr({ type: 'text', value: '' }), LABELS)).toBe(SPECS_EMPTY_CELL);
  });

  it('number: devuelve valueNumber como string', () => {
    expect(formatAttributeValue(attr({ type: 'number', valueNumber: 350 }), LABELS)).toBe('350');
  });

  it('number: valueNumber null colapsa a la celda vacía', () => {
    expect(formatAttributeValue(attr({ type: 'number', valueNumber: null }), LABELS)).toBe(
      SPECS_EMPTY_CELL
    );
  });

  it('number: ignora el campo value', () => {
    expect(
      formatAttributeValue(attr({ type: 'number', value: '350 g', valueNumber: 350 }), LABELS)
    ).toBe('350');
  });

  it('boolean true: usa el label "yes"', () => {
    expect(formatAttributeValue(attr({ type: 'boolean', valueBoolean: true }), LABELS)).toBe('Sí');
  });

  it('boolean false: usa el label "no"', () => {
    expect(formatAttributeValue(attr({ type: 'boolean', valueBoolean: false }), LABELS)).toBe('No');
  });

  it('boolean: valueBoolean null colapsa a la celda vacía', () => {
    expect(formatAttributeValue(attr({ type: 'boolean', valueBoolean: null }), LABELS)).toBe(
      SPECS_EMPTY_CELL
    );
  });
});

describe('formatProductAttributeValue', () => {
  it('text + unidad: apila la unidad al valor', () => {
    expect(
      formatProductAttributeValue(
        { slug: 'material', label: 'Material', type: 'text', value: 'Algodón' },
        LABELS
      )
    ).toBe('Algodón');
  });

  it('number + unidad: "11 oz"', () => {
    expect(
      formatProductAttributeValue(
        { slug: 'capacidad', label: 'Capacidad', type: 'number', unit: 'oz', valueNumber: 11 },
        LABELS
      )
    ).toBe('11 oz');
  });

  it('boolean usa los labels provistos', () => {
    expect(
      formatProductAttributeValue(
        { slug: 'estuche', label: 'Incluye estuche', type: 'boolean', valueBoolean: true },
        LABELS
      )
    ).toBe('Sí');
    expect(
      formatProductAttributeValue(
        { slug: 'estuche', label: 'Incluye estuche', type: 'boolean', valueBoolean: false },
        LABELS
      )
    ).toBe('No');
  });

  it('sin valor poblado colapsa a la celda vacía', () => {
    expect(
      formatProductAttributeValue({ slug: 'x', label: 'X', type: 'text' }, LABELS)
    ).toBe(SPECS_EMPTY_CELL);
  });
});

describe('buildProductSpecRows', () => {
  it('arma filas de tallas con medida y de atributos EAV', () => {
    const rows = buildProductSpecRows(
      [{ slug: 'material', label: 'Material', type: 'text', value: 'Cerámica' }],
      [
        { slug: 'grande', label: 'Grande', value: 11, unit: 'oz' },
        { slug: 'm', label: 'M', value: null, unit: null },
      ],
      LABELS
    );
    expect(rows).toEqual([
      { label: 'Grande', value: '11 oz' },
      { label: 'Material', value: 'Cerámica' },
    ]);
  });

  it('ignora tallas sin medida y tolera listas vacías', () => {
    expect(buildProductSpecRows(undefined, [{ slug: 'm', label: 'M' }], LABELS)).toEqual([]);
    expect(buildProductSpecRows([], [], LABELS)).toEqual([]);
  });
});
