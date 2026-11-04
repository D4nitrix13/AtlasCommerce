/**
 * Tests de la lógica pura de la ficha técnica admin (tallas + atributos).
 *
 * `sizePayloadFromForm` / `attributeValuePayloadFromForm` arman el
 * payload de dominio (camelCase) validando slug kebab-case, unidad,
 * y normalizando `value` de string de input a number|null. Los
 * `*ToApiBody` adaptan al casing snake_case que valida el backend, y
 * los `*FromApi` mapean la respuesta a los mocks de presentación.
 */
import { describe, expect, it } from 'vitest';
import {
  SPECS_SIZE_UNITS,
  attributeDictionaryPayloadFromForm,
  attributeDictionaryToApiBody,
  attributeValueFromApi,
  attributeValuePayloadFromForm,
  attributeValueToApiBody,
  sizeFromApi,
  sizePayloadFromForm,
  sizeToApiBody,
  slugify,
  type AttributeApiRow,
  type AttributeDictionaryFormInput,
  type AttributeValueFormInput,
  type SizeFormInput,
  type SpecsFormError,
} from '../../../src/lib/admin-product-specs';

function sizeInput(overrides: Partial<SizeFormInput> = {}): SizeFormInput {
  return {
    productId: 7,
    slug: '11-oz',
    label: '11 oz',
    value: '11',
    unit: 'oz',
    position: '0',
    ...overrides,
  };
}

function attrInput(overrides: Partial<AttributeValueFormInput> = {}): AttributeValueFormInput {
  return {
    productId: 7,
    attributeId: 3,
    type: 'text',
    rawValue: 'Rojo',
    ...overrides,
  };
}

function codes(errors: SpecsFormError[]): string[] {
  return errors.map((e) => e.code);
}

describe('sizePayloadFromForm', () => {
  it('happy path: arma el payload con value/position normalizados a number', () => {
    const result = sizePayloadFromForm(sizeInput({ value: '11.5', position: '2' }));
    expect(result).toEqual({
      ok: true,
      payload: {
        productId: 7,
        slug: '11-oz',
        label: '11 oz',
        value: 11.5,
        unit: 'oz',
        position: 2,
      },
    });
  });

  it('value vacío → null; unit vacío → null; position vacío → 0', () => {
    const result = sizePayloadFromForm(sizeInput({ value: '  ', unit: '', position: '' }));
    expect(result).toEqual({
      ok: true,
      payload: {
        productId: 7,
        slug: '11-oz',
        label: '11 oz',
        value: null,
        unit: null,
        position: 0,
      },
    });
  });

  it('acepta todas las unidades del set', () => {
    for (const unit of SPECS_SIZE_UNITS) {
      const result = sizePayloadFromForm(sizeInput({ unit }));
      expect(result.ok).toBe(true);
    }
  });

  it('slug vacío → slugRequired', () => {
    const result = sizePayloadFromForm(sizeInput({ slug: '  ' }));
    expect(result.ok).toBe(false);
    if (!result.ok) expect(codes(result.errors)).toEqual(['slugRequired']);
  });

  it('slug fuera de kebab-case → slugInvalid', () => {
    for (const slug of ['11 oz', '11--oz', 'Foo', '11_oz', '-11', '11-']) {
      const result = sizePayloadFromForm(sizeInput({ slug }));
      expect(result.ok).toBe(false);
      if (!result.ok) expect(codes(result.errors)).toEqual(['slugInvalid']);
    }
  });

  it('slug de más de 64 chars → slugTooLong', () => {
    const result = sizePayloadFromForm(sizeInput({ slug: 'a'.repeat(65) }));
    expect(result.ok).toBe(false);
    if (!result.ok) expect(codes(result.errors)).toEqual(['slugTooLong']);
  });

  it('label vacío → labelRequired; label largo → labelTooLong', () => {
    const empty = sizePayloadFromForm(sizeInput({ label: '' }));
    expect(empty.ok).toBe(false);
    if (!empty.ok) expect(codes(empty.errors)).toEqual(['labelRequired']);

    const long = sizePayloadFromForm(sizeInput({ label: 'x'.repeat(65) }));
    expect(long.ok).toBe(false);
    if (!long.ok) expect(codes(long.errors)).toEqual(['labelTooLong']);
  });

  it('value no numérico → valueNotNumeric; negativo → valueNegative', () => {
    const notNum = sizePayloadFromForm(sizeInput({ value: 'abc' }));
    expect(notNum.ok).toBe(false);
    if (!notNum.ok) expect(codes(notNum.errors)).toEqual(['valueNotNumeric']);

    const negative = sizePayloadFromForm(sizeInput({ value: '-3' }));
    expect(negative.ok).toBe(false);
    if (!negative.ok) expect(codes(negative.errors)).toEqual(['valueNegative']);
  });

  it('unit fuera del set → unitInvalid', () => {
    const result = sizePayloadFromForm(sizeInput({ unit: 'g' }));
    expect(result.ok).toBe(false);
    if (!result.ok) expect(codes(result.errors)).toEqual(['unitInvalid']);
  });

  it('position no entera o negativa → positionInvalid', () => {
    for (const position of ['1.5', '-1', 'abc']) {
      const result = sizePayloadFromForm(sizeInput({ position }));
      expect(result.ok).toBe(false);
      if (!result.ok) expect(codes(result.errors)).toEqual(['positionInvalid']);
    }
  });

  it('acumula todos los errores juntos', () => {
    const result = sizePayloadFromForm(sizeInput({ slug: 'BAD', unit: 'g', value: 'x' }));
    expect(result.ok).toBe(false);
    if (!result.ok) expect(codes(result.errors).sort()).toEqual([
      'slugInvalid',
      'unitInvalid',
      'valueNotNumeric',
    ]);
  });
});

describe('attributeValuePayloadFromForm', () => {
  it('text: value poblado, typed fields en null', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'text', rawValue: ' Rojo ' }));
    expect(result).toEqual({
      ok: true,
      payload: { productId: 7, attributeId: 3, value: 'Rojo', valueNumber: null, valueBoolean: null },
    });
  });

  it('select: sigue el branch de texto', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'select', rawValue: 'Mate' }));
    expect(result).toEqual({
      ok: true,
      payload: { productId: 7, attributeId: 3, value: 'Mate', valueNumber: null, valueBoolean: null },
    });
  });

  it('number: valueNumber poblado, value en null', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'number', rawValue: '350.5' }));
    expect(result).toEqual({
      ok: true,
      payload: { productId: 7, attributeId: 3, value: null, valueNumber: 350.5, valueBoolean: null },
    });
  });

  it('boolean: valueBoolean según el input, value en null', () => {
    const yes = attributeValuePayloadFromForm(attrInput({ type: 'boolean', rawValue: 'true' }));
    expect(yes).toEqual({
      ok: true,
      payload: { productId: 7, attributeId: 3, value: null, valueNumber: null, valueBoolean: true },
    });

    const no = attributeValuePayloadFromForm(attrInput({ type: 'boolean', rawValue: 'false' }));
    expect(no).toEqual({
      ok: true,
      payload: { productId: 7, attributeId: 3, value: null, valueNumber: null, valueBoolean: false },
    });
  });

  it('attributeId vacío → attributeRequired', () => {
    const result = attributeValuePayloadFromForm(attrInput({ attributeId: '' }));
    expect(result.ok).toBe(false);
    if (!result.ok) expect(codes(result.errors)).toEqual(['attributeRequired']);
  });

  it('text vacío → valueRequired; text largo → valueTooLong', () => {
    const empty = attributeValuePayloadFromForm(attrInput({ type: 'text', rawValue: '  ' }));
    expect(empty.ok).toBe(false);
    if (!empty.ok) expect(codes(empty.errors)).toEqual(['valueRequired']);

    const long = attributeValuePayloadFromForm(
      attrInput({ type: 'text', rawValue: 'x'.repeat(256) })
    );
    expect(long.ok).toBe(false);
    if (!long.ok) expect(codes(long.errors)).toEqual(['valueTooLong']);
  });

  it('number vacío → valueRequired; no numérico → valueNotNumeric', () => {
    const empty = attributeValuePayloadFromForm(attrInput({ type: 'number', rawValue: '' }));
    expect(empty.ok).toBe(false);
    if (!empty.ok) expect(codes(empty.errors)).toEqual(['valueRequired']);

    const bad = attributeValuePayloadFromForm(attrInput({ type: 'number', rawValue: 'abc' }));
    expect(bad.ok).toBe(false);
    if (!bad.ok) expect(codes(bad.errors)).toEqual(['valueNotNumeric']);
  });

  it('boolean vacío → valueRequired', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'boolean', rawValue: '' }));
    expect(result.ok).toBe(false);
    if (!result.ok) expect(codes(result.errors)).toEqual(['valueRequired']);
  });
});

describe('sizeToApiBody', () => {
  it('expone los keys snake_case que valida el FormRequest del backend', () => {
    const result = sizePayloadFromForm(sizeInput());
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(sizeToApiBody(result.payload)).toEqual({
        product_id: 7,
        slug: '11-oz',
        label: '11 oz',
        value: 11,
        unit: 'oz',
        position: 0,
      });
    }
  });
});

describe('attributeValueToApiBody', () => {
  it('text: manda value y typed fields en null', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'text' }));
    if (result.ok) {
      expect(attributeValueToApiBody(result.payload)).toEqual({
        product_id: 7,
        attribute_id: 3,
        value: 'Rojo',
        value_number: null,
        value_boolean: null,
      });
    }
  });

  it('number: sintetiza value string desde valueNumber (el API exige value)', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'number', rawValue: '350' }));
    if (result.ok) {
      expect(attributeValueToApiBody(result.payload)).toEqual({
        product_id: 7,
        attribute_id: 3,
        value: '350',
        value_number: 350,
        value_boolean: null,
      });
    }
  });

  it('boolean: sintetiza value string desde valueBoolean', () => {
    const result = attributeValuePayloadFromForm(attrInput({ type: 'boolean', rawValue: 'true' }));
    if (result.ok) {
      expect(attributeValueToApiBody(result.payload)).toEqual({
        product_id: 7,
        attribute_id: 3,
        value: 'true',
        value_number: null,
        value_boolean: true,
      });
    }
  });
});

describe('sizeFromApi', () => {
  it('coerce strings decimales del driver a number', () => {
    expect(
      sizeFromApi({ id: 1, productId: 7, slug: '11-oz', label: '11 oz', value: '11.0000', unit: 'oz', position: '2' })
    ).toEqual({ id: 1, productId: 7, slug: '11-oz', label: '11 oz', value: 11, unit: 'oz', position: 2 });
  });

  it('value/unit null se preservan', () => {
    expect(
      sizeFromApi({ id: 2, productId: 7, slug: 'm', label: 'M', value: null, unit: null, position: 0 })
    ).toEqual({ id: 2, productId: 7, slug: 'm', label: 'M', value: null, unit: null, position: 0 });
  });
});

describe('attributeValueFromApi', () => {
  const attr: AttributeApiRow = { id: 3, name: 'Material', slug: 'material', type: 'text' };

  it('text: pasa value y usa name/slug del atributo', () => {
    expect(
      attributeValueFromApi({ id: 9, product_id: 7, attribute_id: 3, value: 'Algodón' }, attr)
    ).toEqual({
      id: 9,
      productId: 7,
      slug: 'material',
      label: 'Material',
      type: 'text',
      unit: null,
      value: 'Algodón',
      valueNumber: null,
      valueBoolean: null,
    });
  });

  it('number: deriva valueNumber desde value cuando el API no manda value_number', () => {
    const row = attributeValueFromApi(
      { id: 9, product_id: 7, attribute_id: 3, value: '350' },
      { ...attr, type: 'number' }
    );
    expect(row.valueNumber).toBe(350);
    expect(row.value).toBeNull();
  });

  it('boolean: deriva valueBoolean desde value "true"/"false"', () => {
    const row = attributeValueFromApi(
      { id: 9, product_id: 7, attribute_id: 3, value: 'true' },
      { ...attr, type: 'boolean' }
    );
    expect(row.valueBoolean).toBe(true);
    expect(row.value).toBeNull();
  });

  it('tipo desconocido del API colapsa a text', () => {
    const row = attributeValueFromApi(
      { id: 9, product_id: 7, attribute_id: 3, value: 'x' },
      { ...attr, type: 'multiselect' }
    );
    expect(row.type).toBe('text');
  });
});

function dictInput(
  overrides: Partial<AttributeDictionaryFormInput> = {}
): AttributeDictionaryFormInput {
  return {
    name: 'Material',
    slug: 'material',
    type: 'select',
    unit: '',
    options: ['Algodón', 'Poliéster'],
    description: 'Tejido del producto',
    isFilterable: true,
    isRequired: false,
    ...overrides,
  };
}

function dictCodes(errors: { code: string }[]): string[] {
  return errors.map((e) => e.code);
}

describe('slugify', () => {
  it('pasa a kebab-case, sin acentos ni caracteres sueltos', () => {
    expect(slugify('Tejido Elástico')).toBe('tejido-elastico');
    expect(slugify('  Color  ')).toBe('color');
    expect(slugify('Poliéster 100%')).toBe('poliester-100');
    expect(slugify('---')).toBe('');
  });
});

describe('attributeDictionaryPayloadFromForm', () => {
  it('happy path: arma el payload con options/normalizados', () => {
    const result = attributeDictionaryPayloadFromForm(dictInput());
    expect(result).toEqual({
      ok: true,
      payload: {
        name: 'Material',
        slug: 'material',
        type: 'select',
        unit: null,
        options: ['Algodón', 'Poliéster'],
        description: 'Tejido del producto',
        isFilterable: true,
        isRequired: false,
      },
    });
  });

  it('slug vacío se autogenera desde name', () => {
    const result = attributeDictionaryPayloadFromForm(
      dictInput({ name: 'Tejido Elástico', slug: '' })
    );
    expect(result.ok).toBe(true);
    if (result.ok) expect(result.payload.slug).toBe('tejido-elastico');
  });

  it('options vacía → null; description vacía → null; unit vacía → null', () => {
    const result = attributeDictionaryPayloadFromForm(
      dictInput({ options: [], description: '  ', unit: '' })
    );
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.payload.options).toBeNull();
      expect(result.payload.description).toBeNull();
      expect(result.payload.unit).toBeNull();
    }
  });

  it('acepta todas las unidades del set en unit', () => {
    for (const unit of SPECS_SIZE_UNITS) {
      const result = attributeDictionaryPayloadFromForm(dictInput({ type: 'number', unit }));
      expect(result.ok).toBe(true);
      if (result.ok) expect(result.payload.unit).toBe(unit);
    }
  });

  it('name vacío → nameRequired; corto → nameTooShort; largo → nameTooLong', () => {
    const empty = attributeDictionaryPayloadFromForm(dictInput({ name: '  ' }));
    expect(empty.ok).toBe(false);
    if (!empty.ok) expect(dictCodes(empty.errors)).toEqual(['nameRequired']);

    const short = attributeDictionaryPayloadFromForm(dictInput({ name: 'a' }));
    expect(short.ok).toBe(false);
    if (!short.ok) expect(dictCodes(short.errors)).toEqual(['nameTooShort']);

    const long = attributeDictionaryPayloadFromForm(dictInput({ name: 'x'.repeat(101) }));
    expect(long.ok).toBe(false);
    if (!long.ok) expect(dictCodes(long.errors)).toEqual(['nameTooLong']);
  });

  it('slug fuera de kebab-case → slugInvalid; corto → slugTooShort; largo → slugTooLong', () => {
    const bad = attributeDictionaryPayloadFromForm(dictInput({ slug: 'Bad Slug' }));
    expect(bad.ok).toBe(false);
    if (!bad.ok) expect(dictCodes(bad.errors)).toEqual(['slugInvalid']);

    const short = attributeDictionaryPayloadFromForm(dictInput({ slug: 'a' }));
    expect(short.ok).toBe(false);
    if (!short.ok) expect(dictCodes(short.errors)).toEqual(['slugTooShort']);

    const long = attributeDictionaryPayloadFromForm(dictInput({ slug: 'a'.repeat(121) }));
    expect(long.ok).toBe(false);
    if (!long.ok) expect(dictCodes(long.errors)).toEqual(['slugTooLong']);
  });

  it('type fuera del set → typeInvalid; unit fuera del set → unitInvalid', () => {
    const badType = attributeDictionaryPayloadFromForm(dictInput({ type: 'multiselect' }));
    expect(badType.ok).toBe(false);
    if (!badType.ok) expect(dictCodes(badType.errors)).toEqual(['typeInvalid']);

    const badUnit = attributeDictionaryPayloadFromForm(dictInput({ type: 'number', unit: 'g' }));
    expect(badUnit.ok).toBe(false);
    if (!badUnit.ok) expect(dictCodes(badUnit.errors)).toEqual(['unitInvalid']);
  });

  it('options: más de 50 → optionsTooMany; opción vacía → optionEmpty; larga → optionTooLong', () => {
    const many = attributeDictionaryPayloadFromForm(
      dictInput({ options: Array.from({ length: 51 }, (_, i) => `op-${i}`) })
    );
    expect(many.ok).toBe(false);
    if (!many.ok) expect(dictCodes(many.errors)).toContain('optionsTooMany');

    const empty = attributeDictionaryPayloadFromForm(dictInput({ options: ['ok', '  '] }));
    expect(empty.ok).toBe(false);
    if (!empty.ok) expect(dictCodes(empty.errors)).toEqual(['optionEmpty']);

    const long = attributeDictionaryPayloadFromForm(
      dictInput({ options: ['x'.repeat(121)] })
    );
    expect(long.ok).toBe(false);
    if (!long.ok) expect(dictCodes(long.errors)).toEqual(['optionTooLong']);
  });

  it('options duplicadas (post-trim) → optionDuplicated', () => {
    const result = attributeDictionaryPayloadFromForm(
      dictInput({ options: ['Algodón', ' Algodón '] })
    );
    expect(result.ok).toBe(false);
    if (!result.ok) expect(dictCodes(result.errors)).toEqual(['optionDuplicated']);
  });

  it('description de más de 5000 chars → descriptionTooLong', () => {
    const result = attributeDictionaryPayloadFromForm(
      dictInput({ description: 'x'.repeat(5001) })
    );
    expect(result.ok).toBe(false);
    if (!result.ok) expect(dictCodes(result.errors)).toEqual(['descriptionTooLong']);
  });

  it('acumula errores de fields distintos juntos', () => {
    const result = attributeDictionaryPayloadFromForm(
      dictInput({ name: '', slug: 'BAD', type: 'multiselect', unit: 'g', options: [''] })
    );
    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(dictCodes(result.errors).sort()).toEqual([
        'nameRequired',
        'optionEmpty',
        'slugInvalid',
        'typeInvalid',
        'unitInvalid',
      ]);
    }
  });
});

describe('attributeDictionaryToApiBody', () => {
  it('expone los keys snake_case que validan los FormRequests del backend', () => {
    const result = attributeDictionaryPayloadFromForm(dictInput({ type: 'number', unit: 'oz' }));
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(attributeDictionaryToApiBody(result.payload)).toEqual({
        name: 'Material',
        slug: 'material',
        type: 'number',
        unit: 'oz',
        options: ['Algodón', 'Poliéster'],
        description: 'Tejido del producto',
        is_filterable: true,
        is_required: false,
      });
    }
  });
});
