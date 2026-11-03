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
  formatAttributeValue,
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
